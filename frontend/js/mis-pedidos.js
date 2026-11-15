"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const usuario = Api.getUsuario();
  if (!usuario) {
    window.location.href = "login.html";
    return;
  }

  const tbodyPedidos = document.getElementById("cuerpo-mis-pedidos");
  const aviso = document.getElementById("aviso-mis-pedidos");
  const formPerfil = document.getElementById("form-perfil");
  const tabsBtns = document.querySelectorAll(".tab-btn");
  const formatearEuro = new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" });

  // Control de pestañas (Mis Pedidos / Editar Mis Datos)
  tabsBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      tabsBtns.forEach((b) => b.classList.remove("activo"));
      btn.classList.add("activo");
      const destino = btn.dataset.tab;
      document.getElementById("tab-pedidos").classList.toggle("oculto", destino !== "tab-pedidos");
      document.getElementById("tab-perfil").classList.toggle("oculto", destino !== "tab-perfil");
    });
  });

  function mostrarAviso(texto, esError = false) {
    aviso.textContent = texto;
    aviso.className = `aviso ${esError ? "aviso--error" : ""}`;
    aviso.hidden = false;
  }

  async function cargarMisPedidos() {
    try {
      const data = await Api.get("/pedidos/mios");
      const pedidos = Array.isArray(data) ? data : data.pedidos || [];

      if (pedidos.length === 0) {
        tbodyPedidos.innerHTML = `<tr><td colspan="7" style="text-align:center;">Aún no has realizado ningún pedido.</td></tr>`;
        return;
      }

      tbodyPedidos.innerHTML = pedidos
        .map((p) => {
          const lineasTexto = Array.isArray(p.lineas)
            ? p.lineas.map((l) => `${l.cantidad}x ${l.producto_nombre || l.nombre} (T.${l.talla})`).join("<br>")
            : `${p.producto_nombre || "Zapatillas"} (T.${p.talla || "-"})`;

          const esPersonalizado = Array.isArray(p.lineas)
            ? p.lineas.some((l) => l.personalizada || l.opciones_personalizacion)
            : Boolean(p.personalizado);

          const cancelable = p.estado === "pendiente" || p.estado === "confirmado";

          return `
            <tr>
              <td><strong>#${p.codigo || p.id}</strong></td>
              <td>${new Date(p.fecha || p.created_at).toLocaleDateString("es-ES")}</td>
              <td>${lineasTexto}</td>
              <td>${esPersonalizado ? "Sí (Club/Custom)" : "Estándar"}</td>
              <td>${formatearEuro.format(Number(p.total || 0))}</td>
              <td><span class="badge-estado estado-${p.estado}">${p.estado}</span></td>
              <td>
                ${
                  cancelable
                    ? `<button type="button" class="btn-accion btn-cancelar" data-cancelar="${p.id}">Cancelar</button>`
                    : `<small style="color:#666;">No modificable</small>`
                }
              </td>
            </tr>
          `;
        })
        .join("");
    } catch (err) {
      tbodyPedidos.innerHTML = `<tr><td colspan="7" style="text-align:center; color:red;">${err.message}</td></tr>`;
    }
  }

  tbodyPedidos.addEventListener("click", async (e) => {
    const id = e.target.dataset.cancelar;
    if (!id) return;

    try {
      await Api.patch(`/pedidos/${id}/cancelar`, {});
      mostrarAviso("Pedido cancelado y stock restituido correctamente.");
      cargarMisPedidos();
    } catch (err) {
      mostrarAviso(err.message, true);
    }
  });

  // Cargar y guardar datos propios del cliente
  function cargarDatosPerfil() {
    document.getElementById("perfil-nombre").value = usuario.nombre || "";
    document.getElementById("perfil-email").value = usuario.email || "";
    document.getElementById("perfil-direccion").value = usuario.direccion || "";
  }

  formPerfil.addEventListener("submit", async (e) => {
    e.preventDefault();
    const payload = {
      nombre: document.getElementById("perfil-nombre").value.trim(),
      email: document.getElementById("perfil-email").value.trim(),
      direccion: document.getElementById("perfil-direccion").value.trim(),
    };
    const nuevaPass = document.getElementById("perfil-password").value;
    if (nuevaPass) payload.password = nuevaPass;

    try {
      const res = await Api.put("/perfil", payload);
      const actualizado = res.usuario || { ...usuario, ...payload };
      delete actualizado.password;
      localStorage.setItem("sf_usuario", JSON.stringify(actualizado));
      alert("Datos actualizados correctamente.");
      document.getElementById("perfil-password").value = "";
    } catch (err) {
      alert(`Error: ${err.message}`);
    }
  });

  cargarMisPedidos();
  cargarDatosPerfil();
});