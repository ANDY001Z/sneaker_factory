"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const formLogin = document.getElementById("form-login");
  const formRegistro = document.getElementById("form-registro");
  const aviso = document.getElementById("aviso-auth");

  function mostrarAviso(texto, esError = false) {
    aviso.textContent = texto;
    aviso.className = `aviso ${esError ? "aviso--error" : ""}`;
    aviso.hidden = false;
  }

  formLogin.addEventListener("submit", async (e) => {
    e.preventDefault();
    const email = document.getElementById("login-email").value.trim();
    const password = document.getElementById("login-password").value;

    try {
      const res = await Api.post("/auth/login", { email, password });
      if (res.token) localStorage.setItem("sf_token", res.token);
      localStorage.setItem("sf_usuario", JSON.stringify(res.usuario));

      if (res.usuario.rol === "admin" || res.usuario.rol === "almacen") {
        window.location.href = "panel.html";
      } else {
        window.location.href = "mis-pedidos.html";
      }
    } catch (err) {
      mostrarAviso(err.message, true);
    }
  });

  formRegistro.addEventListener("submit", async (e) => {
    e.preventDefault();
    const nombre = document.getElementById("reg-nombre").value.trim();
    const email = document.getElementById("reg-email").value.trim();
    const password = document.getElementById("reg-password").value;

    try {
      const res = await Api.post("/auth/register", { nombre, email, password });
      if (res.token) localStorage.setItem("sf_token", res.token);
      if (res.usuario) {
        localStorage.setItem("sf_usuario", JSON.stringify(res.usuario));
        window.location.href = "mis-pedidos.html";
      } else {
        mostrarAviso("Registro completado. Inicia sesión con tu cuenta.");
        formRegistro.reset();
      }
    } catch (err) {
      mostrarAviso(err.message, true);
    }
  });
});