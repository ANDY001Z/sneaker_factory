"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const usuario = Api.getUsuario();
  if (!usuario || (usuario.rol !== "admin" && usuario.rol !== "almacen")) {
    alert("Acceso denegado: Se requiere perfil de Administrador o Empleado de Almacén.");
    window.location.href = "login.html";
    return;
  }

  const badgeRol = document.getElementById("badge-rol-panel");
  if (badgeRol) {
    badgeRol.textContent = `Rol: ${usuario.rol.toUpperCase()}`;
  }

  const formatoMoneda = new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" });
  const tbodyPedidos = document.getElementById("cuerpo-tabla-pedidos");
  const filtroEstado = document.getElementById("filtro-estado");
  const tbodyProductos = document.getElementById("cuerpo-tabla-productos");
  const tbodyAuditoria = document.getElementById("cuerpo-tabla-auditoria");

  // Ajustar enlaces de exportación a las rutas reales del backend
  document.querySelectorAll('a[href*="/pedidos/export"]').forEach((link) => {
    if (link.href.includes("csv")) link.href = "/api/admin/pedidos/exportar?formato=csv";
    if (link.href.includes("json")) link.href = "/api/admin/pedidos/exportar?formato=json";
  });

  const btnNuevoProducto = document.getElementById("btn-nuevo-producto");
  const cajaNuevoProducto = document.getElementById("caja-nuevo-producto");
  const formNuevoProducto = document.getElementById("form-nuevo-producto");

  if (btnNuevoProducto) {
    if (usuario.rol !== "admin") {
      btnNuevoProducto.style.display = "none";
    } else {
      btnNuevoProducto.addEventListener("click", () => {
        cajaNuevoProducto?.classList.toggle("oculto");
      });
    }
  }

  if (formNuevoProducto) {
    formNuevoProducto.addEventListener("submit", async (e) => {
      e.preventDefault();
      const nombre = document.getElementById("np-nombre")?.value.trim() || "";
      const categoriaRaw = (document.getElementById("np-categoria")?.value || "").trim().toLowerCase();
      const categoria = ["lifestyle", "running", "basketball", "skate"].includes(categoriaRaw)
        ? categoriaRaw
        : "lifestyle";
      const color = document.getElementById("np-color")?.value.trim() || "Blanco/Negro";
      const precioBase = Number(document.getElementById("np-precio")?.value);
      const stockInicial = Number(document.getElementById("np-stock")?.value);
      const imagenUrl = document.getElementById("np-imagen")?.value.trim() || "/assets/img/af1.jpg";
      const sku = `SF-${Date.now().toString().slice(-6)}`;

      try {
        const modeloCreado = await Api.post("/admin/modelos", {
          sku,
          marca: "Sneaker Factory",
          nombre,
          descripcion: `Modelo ${nombre} (${color}) - Categoría ${categoria}`,
          categoria,
          precio_base: precioBase,
          imagen_url: imagenUrl,
          personalizable: true,
        });

        if (modeloCreado?.id) {
          await Api.post(`/admin/modelos/${modeloCreado.id}/variantes`, {
            talla: 42.0,
            color,
            stock: stockInicial,
            stock_minimo: 3,
          });
        }

        alert("Modelo y variante inicial creados correctamente.");
        formNuevoProducto.reset();
        cajaNuevoProducto?.classList.add("oculto");
        cargarProductosPanel();
        cargarKPIs();
        cargarAuditoria();
      } catch (err) {
        alert(`Error al crear modelo: ${err.message}`);
      }
    });
  }

  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".tab-btn").forEach((b) => b.classList.remove("activo"));
      btn.classList.add("activo");
      const target = btn.dataset.tab;
      document.querySelectorAll(".seccion-tab").forEach((sec) => {
        sec.classList.toggle("oculto", sec.id !== target);
      });
    });
  });

  // 1. KPIs del Dashboard (/api/admin/dashboard)
  async function cargarKPIs() {
    try {
      const dash = await Api.get("/admin/dashboard");
      const estados = dash.pedidos_por_estado || [];
      const pend = estados.find((e) => e.estado === "pendiente")?.total_pedidos ?? 0;
      const env = estados.find((e) => e.estado === "enviado")?.total_pedidos ?? 0;

      const elPend = document.getElementById("kpi-pedidos-estado");
      const elVend = document.getElementById("kpi-unidades-vendidas");
      const elStock = document.getElementById("kpi-bajo-stock");

      if (elPend) elPend.textContent = `${pend} Pend / ${env} Env`;
      if (elVend) elVend.textContent = `${dash.ventas?.unidades_vendidas ?? 0} pares`;
      if (elStock) elStock.textContent = `${(dash.modelos_menos_existencias || []).length} modelos`;
    } catch (err) {
      console.error("Error cargando KPIs:", err);
    }
  }

  // 2. Gestión de Pedidos (/api/admin/pedidos)
  async function cargarPedidos(estado = "") {
    if (!tbodyPedidos) return;

    try {
      const url = estado
        ? `/admin/pedidos?limit=50&estado=${encodeURIComponent(estado)}`
        : "/admin/pedidos?limit=50";
      const data = await Api.get(url);
      const pedidos = Array.isArray(data) ? data : data.items || [];

      if (pedidos.length === 0) {
        tbodyPedidos.innerHTML = `<tr><td colspan="6" style="text-align:center;">No hay pedidos registrados.</td></tr>`;
        return;
      }

      tbodyPedidos.innerHTML = pedidos
        .map((p) => {
          const detalle = Array.isArray(p.lineas)
            ? p.lineas.map((l) => `${l.cantidad}x ${l.modelo_nombre} (T.${l.talla} · ${l.color})`).join("<br>")
            : "-";

          const nombreCliente = p.cliente?.nombre || "Cliente";
          const emailCliente = p.cliente?.email || "";

          return `
            <tr>
              <td><strong>#${p.codigo_pedido || p.id}</strong></td>
              <td>${nombreCliente}<br><small>${emailCliente}</small></td>
              <td>${detalle}</td>
              <td>${formatoMoneda.format(Number(p.importe_total || 0))}</td>
              <td><span class="badge-estado estado-${p.estado}">${p.estado}</span></td>
              <td>
                <select class="select-estado-pedido" data-id="${p.id}" ${p.estado === "cancelado" || p.estado === "enviado" ? "disabled" : ""}>
                  <option value="pendiente" ${p.estado === "pendiente" ? "selected" : ""}>Pendiente</option>
                  <option value="confirmado" ${p.estado === "confirmado" ? "selected" : ""}>Confirmado</option>
                  <option value="preparacion" ${p.estado === "preparacion" ? "selected" : ""}>En preparación</option>
                  <option value="enviado" ${p.estado === "enviado" ? "selected" : ""}>Enviado</option>
                  <option value="cancelado" ${p.estado === "cancelado" ? "selected" : ""}>Cancelado</option>
                </select>
              </td>
            </tr>
          `;
        })
        .join("");
    } catch (err) {
      tbodyPedidos.innerHTML = `<tr><td colspan="6" style="text-align:center; color:red;">${err.message}</td></tr>`;
    }
  }

  tbodyPedidos?.addEventListener("change", async (e) => {
    if (!e.target.classList.contains("select-estado-pedido")) return;
    const id = e.target.dataset.id;
    const nuevoEstado = e.target.value;

    try {
      await Api.patch(`/admin/pedidos/${id}/estado`, { estado: nuevoEstado });
      cargarPedidos(filtroEstado?.value || "");
      cargarKPIs();
      cargarAuditoria();
    } catch (err) {
      alert(`No se pudo cambiar el estado: ${err.message}`);
      cargarPedidos(filtroEstado?.value || "");
    }
  });

  filtroEstado?.addEventListener("change", (e) => cargarPedidos(e.target.value));

  // 3. Gestión de Productos y Stock por Variante (/api/admin/modelos y /api/admin/variantes/{id}/stock)
  async function cargarProductosPanel() {
    if (!tbodyProductos) return;

    try {
      const data = await Api.get("/admin/modelos?limit=50");
      const productos = Array.isArray(data) ? data : data.items || [];

      const filas = [];
      productos.forEach((p) => {
        const variantes = Array.isArray(p.variantes) ? p.variantes : [];
        if (variantes.length === 0) {
          filas.push(`
            <tr>
              <td>#${p.id}</td>
              <td><strong>${p.marca} ${p.nombre}</strong></td>
              <td>${p.categoria || "-"}</td>
              <td>${formatoMoneda.format(Number(p.precio_base))}</td>
              <td>0 uds. (Sin variantes)</td>
              <td>-</td>
            </tr>
          `);
        } else {
          variantes.forEach((v) => {
            filas.push(`
              <tr>
                <td>#${p.id} (V.${v.id})</td>
                <td><strong>${p.marca} ${p.nombre}</strong><br><small>Talla ${v.talla} · ${v.color}</small></td>
                <td>${p.categoria || "-"}</td>
                <td>${formatoMoneda.format(Number(p.precio_base))}</td>
                <td>${v.stock} uds.</td>
                <td>
                  <input type="number" min="0" value="${v.stock}" id="stock-var-${v.id}" style="width:70px; padding:4px;">
                  <button type="button" class="btn-accion btn-confirmar" data-variante-id="${v.id}">Guardar</button>
                </td>
              </tr>
            `);
          });
        }
      });

      tbodyProductos.innerHTML = filas.join("");
    } catch (err) {
      tbodyProductos.innerHTML = `<tr><td colspan="6" style="color:red;">${err.message}</td></tr>`;
    }
  }

  tbodyProductos?.addEventListener("click", async (e) => {
    const varId = e.target.dataset.varianteId;
    if (!varId) return;
    const inputStock = document.getElementById(`stock-var-${varId}`);
    if (!inputStock) return;
    const nuevoStock = Number(inputStock.value);

    try {
      await Api.patch(`/admin/variantes/${varId}/stock`, { stock: nuevoStock });
      alert("Stock de la variante actualizado.");
      cargarProductosPanel();
      cargarKPIs();
      cargarAuditoria();
    } catch (err) {
      alert(`Error actualizando stock: ${err.message}`);
    }
  });

  // 4. Informes (/api/admin/informes/ventas-modelos y /api/admin/informes/clientes)
  async function cargarInformes() {
    try {
      const tablaModelos = document.getElementById("tabla-informe-modelos");
      if (tablaModelos) {
        const ventas = await Api.get("/admin/informes/ventas-modelos");
        tablaModelos.innerHTML = (ventas.items || [])
          .map(
            (m) => `
            <tr>
              <td>${m.marca} ${m.nombre}</td>
              <td>${m.unidades_vendidas}</td>
              <td>${formatoMoneda.format(Number(m.ingresos_brutos || 0))}</td>
            </tr>
          `
          )
          .join("");
      }

      const tablaClientes = document.getElementById("tabla-informe-clientes");
      if (tablaClientes) {
        if (usuario.rol === "admin") {
          const clientes = await Api.get("/admin/informes/clientes");
          tablaClientes.innerHTML = (clientes.items || [])
            .map(
              (c) => `
              <tr>
                <td>${c.cliente}</td>
                <td>${c.pedidos_validos}</td>
                <td>${formatoMoneda.format(Number(c.gasto_total || 0))}</td>
              </tr>
            `
            )
            .join("");
        } else {
          tablaClientes.innerHTML =
            `<tr><td colspan="3" style="text-align:center;">Solo visible para perfil Admin.</td></tr>`;
        }
      }
    } catch (err) {
      console.error("Error cargando informes:", err);
    }
  }

  // Importación JSON validada (/api/admin/stock/importar)
  const inputImportar = document.getElementById("input-importar");
  if (inputImportar) {
    inputImportar.addEventListener("change", async (e) => {
      const archivo = e.target.files[0];
      if (!archivo) return;

      try {
        const texto = await archivo.text();
        let items = [];
        if (archivo.name.endsWith(".json")) {
          const parsed = JSON.parse(texto);
          items = Array.isArray(parsed) ? parsed : parsed.items || [];
        } else {
          // Soporte CSV simple: sku,talla,color,stock
          const lineas = texto.split(/\r?\n/).filter((l) => l.trim());
          const cab = lineas[0].toLowerCase().split(/[,;]/).map((s) => s.trim());
          items = lineas.slice(1).map((linea) => {
            const cols = linea.split(/[,;]/).map((s) => s.trim());
            const obj = {};
            cab.forEach((k, idx) => {
              obj[k] = k === "talla" || k === "stock" || k === "stock_minimo" ? Number(cols[idx]) : cols[idx];
            });
            return obj;
          });
        }

        const res = await Api.post("/admin/stock/importar", { items });
        alert(`Importación completada: ${res.procesados ?? items.length} variantes actualizadas.`);
        cargarProductosPanel();
        cargarKPIs();
        cargarAuditoria();
      } catch (err) {
        alert(`Error en importación: ${err.message}`);
      } finally {
        inputImportar.value = "";
      }
    });
  }

  // 5. Auditoría (/api/admin/auditoria — solo admin)
  async function cargarAuditoria() {
    if (!tbodyAuditoria) return;

    if (usuario.rol !== "admin") {
      tbodyAuditoria.innerHTML = `<tr><td colspan="5" style="text-align:center;">El registro de auditoría solo es accesible para Admin.</td></tr>`;
      return;
    }

    try {
      const logs = await Api.get("/admin/auditoria");
      const lista = Array.isArray(logs) ? logs : logs.items || [];

      tbodyAuditoria.innerHTML = lista
        .map(
          (log) => `
          <tr>
            <td>${new Date(log.creado_en).toLocaleString("es-ES")}</td>
            <td>${log.usuario_email || "Sistema"}</td>
            <td><strong>${log.tipo_evento}</strong></td>
            <td>${log.entidad} ${log.entidad_id ? `#${log.entidad_id}` : ""}</td>
            <td>${log.resultado}</td>
          </tr>
        `
        )
        .join("");
    } catch (err) {
      console.error("Error cargando auditoría:", err);
    }
  }

  cargarKPIs();
  cargarPedidos();
  cargarProductosPanel();
  cargarInformes();
  cargarAuditoria();
});