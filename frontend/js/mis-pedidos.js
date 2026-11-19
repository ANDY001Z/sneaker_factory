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
      const data = await Api.get("/pedidos?limit=50");
      const pedidos = Array.isArray(data) ? data : data.items || [];

      if (pedidos.length === 0) {
        tbodyPedidos.innerHTML = `<tr><td colspan="7" style="text-align:center;">Aún no has realizado ningún pedido.</td></tr>`;
        return;
      }

      tbodyPedidos.innerHTML = pedidos
        .map((p) => {
          const lineasTexto = Array.isArray(p.lineas)
            ? p.lineas.map((l) => `${l.cantidad}x ${l.modelo_nombre} (T.${l.talla} · ${l.color})`).join("<br>")
            : "-";

          const cancelable = p.estado === "pendiente" || p.estado === "confirmado";

          return `
            <tr>
              <td><strong>#${p.codigo_pedido || p.id}</strong></td>
              <td>${new Date(p.creado_en).toLocaleDateString("es-ES")}</td>
              <td>${lineasTexto}</td>
              <td>${p.es_personalizado ? "Sí (Club/Custom)" : "Estándar"}</td>
              <td>${formatearEuro.format(Number(p.importe_total || 0))}</td>
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
      await Api.patch(`/pedidos/${id}/cancelar`, { motivo: "Cancelado por el cliente desde su área" });
      mostrarAviso("Pedido cancelado y stock restituido correctamente.");
      cargarMisPedidos();
    } catch (err) {
      mostrarAviso(err.message, true);
    }
  });

  function cargarDatosPerfil() {
    document.getElementById("perfil-nombre").value = usuario.nombre || "";
    if (document.getElementById("perfil-apellidos")) {
      document.getElementById("perfil-apellidos").value = usuario.apellidos || "";
    }
    document.getElementById("perfil-email").value = usuario.email || "";
    document.getElementById("perfil-direccion").value = usuario.direccion || "";
  }

  formPerfil.addEventListener("submit", async (e) => {
    e.preventDefault();
    const payload = {
      nombre: document.getElementById("perfil-nombre").value.trim(),
      apellidos: document.getElementById("perfil-apellidos").value.trim(),
      email: document.getElementById("perfil-email").value.trim(),
      direccion: document.getElementById("perfil-direccion").value.trim() || null,
    };
    const nuevaPass = document.getElementById("perfil-password").value;
    const passActual = document.getElementById("perfil-password-actual")?.value || "";
    if (nuevaPass) {
      payload.current_password = passActual;
      payload.new_password = nuevaPass;
    }

    try {
      const res = await Api.put("/profile", payload);
      const actualizado = res.user || res.usuario || res;
      localStorage.setItem("sf_usuario", JSON.stringify(actualizado));
      alert("Datos actualizados correctamente.");
      document.getElementById("perfil-password").value = "";
      if (document.getElementById("perfil-password-actual")) {
        document.getElementById("perfil-password-actual").value = "";
      }
    } catch (err) {
      alert(`Error: ${err.message}`);
    }
  });

  cargarMisPedidos();
  cargarDatosPerfil();
});