<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Validator;
use App\Repositories\ModelRepository;
use App\Repositories\VariantRepository;

final class CatalogService
{
    private const ALLOWED_CATEGORIES = ['lifestyle', 'running', 'basketball', 'skate'];

    private ModelRepository $models;
    private VariantRepository $variants;
    private AuditService $audit;

    public function __construct()
    {
        $this->models = new ModelRepository();
        $this->variants = new VariantRepository();
        $this->audit = new AuditService();
    }

    public function listCatalog(array $query, bool $includeInactive = false): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = max(1, min(50, (int) ($query['limit'] ?? 12)));

        $filters = [];

        if (isset($query['q']) && is_string($query['q']) && trim($query['q']) !== '') {
            $filters['q'] = trim($query['q']);
        }

        if (isset($query['categoria']) && is_string($query['categoria']) && trim($query['categoria']) !== '') {
            $categoria = strtolower(trim($query['categoria']));
            if (!in_array($categoria, self::ALLOWED_CATEGORIES, true)) {
                throw new HttpException(400, 'Categoría de filtro no válida.', 'INVALID_FILTER');
            }
            $filters['categoria'] = $categoria;
        }

        if (isset($query['marca']) && is_string($query['marca']) && trim($query['marca']) !== '') {
            $filters['marca'] = trim($query['marca']);
        }

        if (isset($query['color']) && is_string($query['color']) && trim($query['color']) !== '') {
            $filters['color'] = trim($query['color']);
        }

        if (isset($query['talla']) && $query['talla'] !== '') {
            if (!is_numeric($query['talla'])) {
                throw new HttpException(400, 'El filtro de talla debe ser numérico.', 'INVALID_FILTER');
            }
            $filters['talla'] = (float) $query['talla'];
        }

        if (isset($query['personalizable']) && $query['personalizable'] !== '') {
            $parsedBool = filter_var($query['personalizable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsedBool === null) {
                throw new HttpException(400, 'El filtro personalizable debe ser true o false.', 'INVALID_FILTER');
            }
            $filters['personalizable'] = $parsedBool;
        }

        if (isset($query['min_precio']) && $query['min_precio'] !== '') {
            if (!is_numeric($query['min_precio']) || (float) $query['min_precio'] < 0) {
                throw new HttpException(400, 'El precio mínimo no es válido.', 'INVALID_FILTER');
            }
            $filters['min_precio'] = (float) $query['min_precio'];
        }

        if (isset($query['max_precio']) && $query['max_precio'] !== '') {
            if (!is_numeric($query['max_precio']) || (float) $query['max_precio'] < 0) {
                throw new HttpException(400, 'El precio máximo no es válido.', 'INVALID_FILTER');
            }
            $filters['max_precio'] = (float) $query['max_precio'];
        }

        if (isset($query['solo_con_stock']) && $query['solo_con_stock'] !== '') {
            $filters['solo_con_stock'] = filter_var($query['solo_con_stock'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($query['orden']) && is_string($query['orden'])) {
            $filters['orden'] = $query['orden'];
        }

        $result = $this->models->search($filters, $page, $limit, $includeInactive);

        $items = [];
        foreach ($result['items'] as $model) {
            $items[] = $model->toArray();
        }

        $total = $result['total'];
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return [
            'items' => $items,
            'meta'  => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function getModelDetail(int $id, bool $onlyActive = true): array
    {
        $model = $this->models->findById($id, $onlyActive);
        if ($model === null) {
            throw new HttpException(404, 'Modelo de zapatilla no encontrado.', 'MODEL_NOT_FOUND');
        }

        return $model->toArray();
    }

    public function createModel(array $input, int $actorId, string $clientIp): array
    {
        $validated = $this->validateModelPayload($input, false);

        if ($this->models->skuExists($validated['sku'])) {
            throw new HttpException(409, 'Ya existe un modelo con ese SKU.', 'SKU_ALREADY_EXISTS');
        }

        $created = $this->models->create($validated);

        $this->audit->record(
            $actorId,
            'CREACION_MODELO',
            'modelo',
            $created->id,
            'OK',
            ['sku' => $created->sku, 'nombre' => $created->nombre],
            $clientIp
        );

        return $created->toArray();
    }

    public function updateModel(int $id, array $input, int $actorId, string $clientIp): array
    {
        $existing = $this->models->findById($id, false);
        if ($existing === null) {
            throw new HttpException(404, 'Modelo de zapatilla no encontrado.', 'MODEL_NOT_FOUND');
        }

        $validated = $this->validateModelPayload($input, true);

        if ($this->models->skuExists($validated['sku'], $id)) {
            throw new HttpException(409, 'Ese SKU ya pertenece a otro modelo.', 'SKU_ALREADY_EXISTS');
        }

        $updated = $this->models->update($id, $validated);

        $this->audit->record(
            $actorId,
            'ACTUALIZACION_MODELO',
            'modelo',
            $updated->id,
            'OK',
            ['sku' => $updated->sku, 'precio_base' => $updated->precioBase, 'activo' => $updated->activo],
            $clientIp
        );

        return $updated->toArray();
    }

    public function deactivateModel(int $id, int $actorId, string $clientIp): void
    {
        $existing = $this->models->findById($id, false);
        if ($existing === null) {
            throw new HttpException(404, 'Modelo de zapatilla no encontrado.', 'MODEL_NOT_FOUND');
        }

        $this->models->deactivate($id);

        $this->audit->record(
            $actorId,
            'BAJA_MODELO',
            'modelo',
            $id,
            'OK',
            ['sku' => $existing->sku],
            $clientIp
        );
    }

    public function createVariant(int $modelId, array $input, int $actorId, string $clientIp): array
    {
        $model = $this->models->findById($modelId, false);
        if ($model === null) {
            throw new HttpException(404, 'Modelo de zapatilla no encontrado.', 'MODEL_NOT_FOUND');
        }

        $validator = new Validator();
        $talla       = $validator->decimal($input, 'talla', 34.0, 50.0);
        $color       = $validator->requiredString($input, 'color', 2, 50);
        $stock       = $validator->nonNegativeInt($input, 'stock', 50000);
        $stockMinimo = isset($input['stock_minimo'])
            ? $validator->nonNegativeInt($input, 'stock_minimo', 5000)
            : 3;
        $validator->failIfErrors();

        if ($this->variants->combinationExists($modelId, $talla, $color)) {
            throw new HttpException(409, 'Ya existe una variante con esa talla y color para este modelo.', 'VARIANT_ALREADY_EXISTS');
        }

        $created = $this->variants->create([
            'modelo_id'    => $modelId,
            'talla'        => $talla,
            'color'        => $color,
            'stock'        => $stock,
            'stock_minimo' => $stockMinimo,
        ]);

        $this->audit->record(
            $actorId,
            'ALTA_VARIANTE',
            'variante_modelo',
            $created->id,
            'OK',
            ['modelo_id' => $modelId, 'talla' => $talla, 'color' => $color, 'stock' => $stock],
            $clientIp
        );

        return $created->toArray();
    }

    public function adjustStock(int $variantId, array $input, int $actorId, string $clientIp): array
    {
        $existing = $this->variants->findById($variantId);
        if ($existing === null) {
            throw new HttpException(404, 'Variante no encontrada.', 'VARIANT_NOT_FOUND');
        }

        $validator = new Validator();
        $newStock = $validator->nonNegativeInt($input, 'stock', 50000);
        $newMinStock = array_key_exists('stock_minimo', $input)
            ? $validator->nonNegativeInt($input, 'stock_minimo', 5000)
            : null;
        $motivo = $validator->optionalString($input, 'motivo', 150);
        $validator->failIfErrors();

        $updated = $this->variants->updateStock($variantId, $newStock, $newMinStock);

        $this->audit->record(
            $actorId,
            'AJUSTE_STOCK',
            'variante_modelo',
            $variantId,
            'OK',
            [
                'stock_anterior' => $existing->stock,
                'stock_nuevo'    => $updated->stock,
                'motivo'         => $motivo ?? 'Ajuste manual desde panel interno',
            ],
            $clientIp
        );

        return $updated->toArray();
    }

    private function validateModelPayload(array $input, bool $includeActivo): array
    {
        $validator = new Validator();
        $sku         = strtoupper($validator->requiredString($input, 'sku', 3, 30));
        $marca       = $validator->requiredString($input, 'marca', 2, 60);
        $nombre      = $validator->requiredString($input, 'nombre', 2, 120);
        $descripcion = $validator->requiredString($input, 'descripcion', 5, 2000);
        $categoria   = $validator->inList($input, 'categoria', self::ALLOWED_CATEGORIES);
        $precioBase  = $validator->decimal($input, 'precio_base', 0.01, 9999.99);
        $imagenUrl   = $validator->requiredString($input, 'imagen_url', 5, 255);
        $validator->failIfErrors();

        $personalizable = isset($input['personalizable'])
            ? filter_var($input['personalizable'], FILTER_VALIDATE_BOOLEAN)
            : false;

        $data = [
            'sku'            => $sku,
            'marca'          => $marca,
            'nombre'         => $nombre,
            'descripcion'    => $descripcion,
            'categoria'      => $categoria,
            'precio_base'    => $precioBase,
            'imagen_url'     => $imagenUrl,
            'personalizable' => $personalizable,
        ];

        if ($includeActivo) {
            $data['activo'] = array_key_exists('activo', $input)
                ? filter_var($input['activo'], FILTER_VALIDATE_BOOLEAN)
                : true;
        }

        return $data;
    }
}