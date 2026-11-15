"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const usuario = Api.getUsuario();
  if (!usuario || (usuario.rol !== "admin" && usuario.rol !== "almacen")) {
    alert("Acceso denegado: Se requiere perfil de Administrador o Empleado de Almacén.");
    window.location.href = "login.html";
    return;
  }

  document.getElementById("badge-rol-panel").textContent = `Rol: ${usuario.rol.toUpperCase()}`;

  const formatoMoneda = new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" });
  const tbodyPedidos = document.getElementById("cuerpo-tabla-pedidos");
  const filtroEstado = document.getElementById("filtro-estado");
  const tbodyProductos = document.getElementById("cuerpo-tabla-productos");
  const tbodyAuditoria = document.getElementById("cuerpo-tabla-auditoria");

  // Ocultar botón de crear producto si no es admin
  const btnNuevoProducto = document.getElementById("btn-nuevo-producto");
  if (btnNuevoProducto && usuario.rol !== "admin") {
    btnNuevoProducto.style.display = "none";
  }

  // Navegación entre las 4 pestañas del panel
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

  // 1. Cargar KPIs del Dashboard
  async function cargarKPIs() {
    try {
      const kpis = await Api.get("/panel/kpis");
      document.getElementById("kpi-pedidos-estado").textContent =
        `${kpis.pendientes ?? 0} Pend / ${kpis.enviados ?? 0} Env`;
      document.getElementById("kpi-unidades-vendidas").textContent = `${kpis.unidades_vendidas ?? 0} pares`;
      document.getElementById("kpi-bajo-stock").textContent = `${kpis.modelos_bajo_stock ?? 0} modelos`;
    } catch (err) {
      console.error("Error cargando KPIs:", err);
    }
  }

  // 2. Gestión de Pedidos y transición de estados
  async function cargarPedidos(estado = "") {
    try {
      const url = estado ? `/gestion/pedidos?estado=${encodeURIComponent(estado)}` : "/gestion/pedidos";
      const data = await Api.get(url);
      const pedidos = Array.isArray(data) ? data : data.pedidos || [];

      if (pedidos.length === 0) {
        tbodyPedidos.innerHTML = `<tr><td colspan="6" style="text-align:center;">No hay pedidos registrados.</td></tr>`;
        return;
      }

      tbodyPedidos.innerHTML = pedidos
        .map((p) => {
          const detalle = Array.isArray(p.lineas)
            ? p.lineas.map((l) => `${l.cantidad}x ${l.producto_nombre} (T.${l.talla})`).join("<br>")
            : `${p.producto_nombre || "Pedido"} (T.${p.talla || "-"})`;

          return `
            <tr>
              <td><strong>#${p.codigo || p.id}</strong></td>
              <td>${p.nombre_cliente}<br><small>${p.email}</small></td>
              <td>${detalle}</td>
              <td>${formatoMoneda.format(Number(p.total || 0))}</td>
              <td><span class="badge-estado estado-${p.estado}">${p.estado}</span></td>
              <td>
                <select class="select-estado-pedido" data-id="${p.id}" ${p.estado === "cancelado" ? "disabled" : ""}>
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

  tbodyPedidos.addEventListener("change", async (e) => {
    if (!e.target.classList.contains("select-estado-pedido")) return;
    const id = e.target.dataset.id;
    const nuevoEstado = e.target.value;

    try {
      await Api.patch(`/gestion/pedidos/${id}`, { estado: nuevoEstado });
      cargarPedidos(filtroEstado.value);
      cargarKPIs();
      cargarAuditoria();
    } catch (err) {
      alert(`No se pudo cambiar el estado: ${err.message}`);
      cargarPedidos(filtroEstado.value);
    }
  });

  filtroEstado.addEventListener("change", (e) => cargarPedidos(e.target.value));

  // 3. Gestión de Productos y Stock
  async function cargarProductosPanel() {
    try {
      const data = await Api.get("/productos");
      const productos = Array.isArray(data) ? data : data.productos || [];

      tbodyProductos.innerHTML = productos
        .map((p) => {
          const stock = Array.isArray(p.variantes)
            ? p.variantes.reduce((acc, v) => acc + Number(v.stock || 0), 0)
            : Number(p.stock_total ?? 0);

          return `
            <tr>
              <td>#${p.id}</td>
              <td><strong>${p.nombre}</strong></td>
              <td>${p.categoria || "-"}</td>
              <td>${formatoMoneda.format(Number(p.precio))}</td>
              <td>${stock} uds.</td>
              <td>
                <input type="number" min="0" value="${stock}" id="stock-prod-${p.id}" style="width:70px; padding:4px;">
                <button type="button" class="btn-accion btn-confirmar" data-stock-id="${p.id}">Guardar</button>
              </td>
            </tr>
          `;
        })
        .join("");
    } catch (err) {
      tbodyProductos.innerHTML = `<tr><td colspan="6" style="color:red;">${err.message}</td></tr>`;
    }
  }

  tbodyProductos.addEventListener("click", async (e) => {
    const id = e.target.dataset.stockId;
    if (!id) return;
    const nuevoStock = Number(document.getElementById(`stock-prod-${id}`).value);

    try {
      await Api.patch(`/gestion/productos/${id}/stock`, { stock: nuevoStock });
      alert("Stock actualizado.");
      cargarProductosPanel();
      cargarKPIs();
      cargarAuditoria();
    } catch (err) {
      alert(`Error actualizando stock: ${err.message}`);
    }
  });

  // 4. Informes (JOINs) e Importación de archivos
  async function cargarInformes() {
    try {
      const inf = await Api.get("/gestion/informes");
      document.getElementById("tabla-informe-modelos").innerHTML = (inf.ventas_por_modelo || [])
        .map(
          (m) => `
          <tr>
            <td>${m.modelo}</td>
            <td>${m.unidades}</td>
            <td>${formatoMoneda.format(Number(m.ingresos || 0))}</td>
          </tr>
        `
        )
        .join("");

      document.getElementById("tabla-informe-clientes").innerHTML = (inf.top_clientes || [])
        .map(
          (c) => `
          <tr>
            <td>${c.cliente}</td>
            <td>${c.total_pedidos}</td>
            <td>${formatoMoneda.format(Number(c.total_gastado || 0))}</td>
          </tr>
        `
        )
        .join("");
    } catch (err) {
      console.error("Error cargando informes:", err);
    }
  }

  const inputImportar = document.getElementById("input-importar");
  if (inputImportar) {
    inputImportar.addEventListener("change", async (e) => {
      const archivo = e.target.files[0];
      if (!archivo) return;
      const formData = new FormData();
      formData.append("archivo", archivo);

      try {
        const res = await Api.post("/gestion/importar", formData);
        alert(res.mensaje || "Datos importados y validados correctamente.");
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

  // 5. Registro de Auditoría
  async function cargarAuditoria() {
    try {
      const logs = await Api.get("/gestion/auditoria");
      const lista = Array.isArray(logs) ? logs : logs.auditoria || [];

      tbodyAuditoria.innerHTML = lista
        .map(
          (log) => `
          <tr>
            <td>${new Date(log.fecha).toLocaleString("es-ES")}</td>
            <td>${log.usuario || "Sistema"}</td>
            <td><strong>${log.tipo_evento || log.accion}</strong></td>
            <td>${log.entidad}</td>
            <td>${log.resultado}</td>
          </tr>
        `
        )
        .join("");
    } catch (err) {
      console.error("Error cargando auditoría:", err);
    }
  }

  // Inicialización general del panel
  cargarKPIs();
  cargarPedidos();
  cargarProductosPanel();
  cargarInformes();
  cargarAuditoria();
});