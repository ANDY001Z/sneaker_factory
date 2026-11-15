"use strict";

const gridCatalogo = document.getElementById("grid-catalogo");
const loaderCatalogo = document.getElementById("loader-catalogo");
const mensajeCatalogo = document.getElementById("mensaje-catalogo");
const contadorResultados = document.getElementById("contador-resultados");
const paginacionCatalogo = document.getElementById("paginacion-catalogo");

const inputBusqueda = document.getElementById("filtro-busqueda");
const selectCategoria = document.getElementById("filtro-categoria");
const selectColor = document.getElementById("filtro-color");
const selectTalla = document.getElementById("filtro-talla");
const selectOrden = document.getElementById("filtro-orden");
const btnLimpiarFiltros = document.getElementById("btn-limpiar-filtros");

const formatearEuro = new Intl.NumberFormat("es-ES", {
  style: "currency",
  currency: "EUR",
});

let productosAPI = [];
let paginaActual = 1;
const ITEMS_POR_PAGINA = 8;

function mostrarLoader(visible) {
  if (loaderCatalogo) loaderCatalogo.classList.toggle("oculto", !visible);
}

function mostrarMensaje(texto, tipo = "vacio") {
  if (mensajeCatalogo) {
    mensajeCatalogo.textContent = texto;
    mensajeCatalogo.className = `mensaje-estado ${tipo}`;
  }
}

function ocultarMensaje() {
  if (mensajeCatalogo) {
    mensajeCatalogo.className = "mensaje-estado oculto";
    mensajeCatalogo.textContent = "";
  }
}

function cargarCategoriasEnSelect(productos) {
  if (!selectCategoria) return;
  const categoriasUnicas = [...new Set(productos.map((p) => p.categoria).filter(Boolean))];
  selectCategoria.innerHTML =
    '<option value="">Todas las categorías</option>' +
    categoriasUnicas.map((cat) => `<option value="${cat}">${cat}</option>`).join("");
}

function obtenerStockDisponible(producto) {
  if (Array.isArray(producto.variantes)) {
    return producto.variantes.reduce((acc, v) => acc + Number(v.stock || 0), 0);
  }
  if (producto.stock_total !== undefined) return Number(producto.stock_total);
  return null;
}

function renderizarCatalogo(listaProductos) {
  if (!gridCatalogo) return;
  gridCatalogo.innerHTML = "";
  if (paginacionCatalogo) paginacionCatalogo.innerHTML = "";

  if (!listaProductos.length) {
    if (contadorResultados) contadorResultados.textContent = "0 modelos encontrados";
    mostrarMensaje("No hay zapatillas que coincidan con los filtros seleccionados.", "vacio");
    return;
  }

  ocultarMensaje();
  if (contadorResultados) {
    contadorResultados.textContent = `Mostrando ${listaProductos.length} ${listaProductos.length === 1 ? "modelo" : "modelos"}`;
  }

  const totalPaginas = Math.ceil(listaProductos.length / ITEMS_POR_PAGINA);
  const inicio = (paginaActual - 1) * ITEMS_POR_PAGINA;
  const productosPagina = listaProductos.slice(inicio, inicio + ITEMS_POR_PAGINA);

  productosPagina.forEach((producto) => {
    const tarjeta = document.createElement("article");
    tarjeta.className = "producto";

    const imagenUrl = producto.imagen
      ? producto.imagen
      : `https://placehold.co/400x260/181818/ffffff?text=${encodeURIComponent(producto.nombre)}`;

    const stockTotal = obtenerStockDisponible(producto);
    const hayStock = stockTotal === null || stockTotal > 0;
    const textoStock =
      stockTotal === null
        ? "Disponible en tienda"
        : hayStock
          ? `En stock (${stockTotal} uds.)`
          : "Agotado temporalmente";

    const tallasDisp = Array.isArray(producto.variantes)
      ? producto.variantes.filter((v) => v.stock > 0).map((v) => v.talla).join(", ") || "Sin stock"
      : "39 - 45";

    tarjeta.innerHTML = `
      <div>
        <figure>
          <img src="${imagenUrl}" alt="Zapatilla ${producto.nombre}" loading="lazy">
        </figure>
        <span class="etiqueta-categoria">${producto.categoria || "Sneakers"}</span>
        <h3>${producto.marca ? `${producto.marca} ` : ""}${producto.nombre}</h3>
        <p class="producto-desc">${producto.descripcion || "Diseño exclusivo Sneaker Factory."}</p>
        <p class="producto-desc"><strong>Color:</strong> ${producto.color || "Multicolor"} | <strong>Tallas:</strong> ${tallasDisp}</p>
      </div>
      <div>
        <p class="producto-stock ${hayStock ? "stock-ok" : "stock-agotado"}">${textoStock}</p>
        <div class="producto-pie">
          <p class="precio">${formatearEuro.format(Number(producto.precio))}</p>
          <a href="html/pedidos.html?modelo=${encodeURIComponent(producto.id)}" class="btn-sm">
            ${hayStock ? "Pedir" : "Ver"}
          </a>
        </div>
      </div>
    `;

    gridCatalogo.appendChild(tarjeta);
  });

  if (paginacionCatalogo && totalPaginas > 1) {
    for (let i = 1; i <= totalPaginas; i++) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = `tab-btn ${i === paginaActual ? "activo" : ""}`;
      btn.textContent = i;
      btn.addEventListener("click", () => {
        paginaActual = i;
        renderizarCatalogo(listaProductos);
      });
      paginacionCatalogo.appendChild(btn);
    }
  }
}

function aplicarFiltrosYOrden() {
  const texto = inputBusqueda ? inputBusqueda.value.trim().toLowerCase() : "";
  const categoria = selectCategoria ? selectCategoria.value : "";
  const color = selectColor ? selectColor.value.toLowerCase() : "";
  const talla = selectTalla ? selectTalla.value : "";
  const orden = selectOrden ? selectOrden.value : "";

  let filtrados = productosAPI.filter((producto) => {
    const coincideTexto =
      !texto ||
      producto.nombre.toLowerCase().includes(texto) ||
      (producto.marca && producto.marca.toLowerCase().includes(texto)) ||
      (producto.descripcion && producto.descripcion.toLowerCase().includes(texto));

    const coincideCategoria = !categoria || producto.categoria === categoria;
    const coincideColor = !color || (producto.color && producto.color.toLowerCase().includes(color));

    let coincideTalla = true;
    if (talla) {
      if (Array.isArray(producto.variantes)) {
        coincideTalla = producto.variantes.some((v) => String(v.talla) === String(talla) && Number(v.stock) > 0);
      } else if (Array.isArray(producto.tallas)) {
        coincideTalla = producto.tallas.map(String).includes(String(talla));
      }
    }

    return coincideTexto && coincideCategoria && coincideColor && coincideTalla;
  });

  if (orden === "precio-asc") filtrados.sort((a, b) => Number(a.precio) - Number(b.precio));
  else if (orden === "precio-desc") filtrados.sort((a, b) => Number(b.precio) - Number(a.precio));

  paginaActual = 1;
  renderizarCatalogo(filtrados);
}

async function cargarProductosDesdeAPI() {
  mostrarLoader(true);
  ocultarMensaje();
  if (gridCatalogo) gridCatalogo.innerHTML = "";
  if (contadorResultados) contadorResultados.textContent = "Conectando con el catálogo...";

  try {
    const datos = await Api.get("/productos");
    const lista = Array.isArray(datos) ? datos : datos.productos || datos.data || [];
    productosAPI = lista.filter((p) => p.activo !== false);
    cargarCategoriasEnSelect(productosAPI);
    renderizarCatalogo(productosAPI);
  } catch (error) {
    console.error("Error al cargar /api/productos:", error);
    if (contadorResultados) contadorResultados.textContent = "Error de conexión";
    mostrarMensaje("No se pudieron cargar los productos desde la API.", "error");
  } finally {
    mostrarLoader(false);
  }
}

if (inputBusqueda) inputBusqueda.addEventListener("input", aplicarFiltrosYOrden);
if (selectCategoria) selectCategoria.addEventListener("change", aplicarFiltrosYOrden);
if (selectColor) selectColor.addEventListener("change", aplicarFiltrosYOrden);
if (selectTalla) selectTalla.addEventListener("change", aplicarFiltrosYOrden);
if (selectOrden) selectOrden.addEventListener("change", aplicarFiltrosYOrden);

if (btnLimpiarFiltros) {
  btnLimpiarFiltros.addEventListener("click", () => {
    if (inputBusqueda) inputBusqueda.value = "";
    if (selectCategoria) selectCategoria.value = "";
    if (selectColor) selectColor.value = "";
    if (selectTalla) selectTalla.value = "";
    if (selectOrden) selectOrden.value = "";
    paginaActual = 1;
    renderizarCatalogo(productosAPI);
  });
}

document.addEventListener("DOMContentLoaded", cargarProductosDesdeAPI);