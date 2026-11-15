"use strict";

const API_BASE = "/api";

const Api = {
  async request(endpoint, options = {}) {
    const token = localStorage.getItem("sf_token");
    const headers = {
      ...(options.body instanceof FormData ? {} : { "Content-Type": "application/json" }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    };

    const res = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      headers,
      credentials: "include",
    });

    const isJson = (res.headers.get("content-type") || "").includes("application/json");
    const data = isJson ? await res.json() : await res.text();

    if (!res.ok) {
      throw new Error((data && (data.error || data.message)) || `Error HTTP ${res.status}`);
    }
    return data;
  },

  get: (url) => Api.request(url, { method: "GET" }),
  post: (url, body) =>
    Api.request(url, {
      method: "POST",
      body: body instanceof FormData ? body : JSON.stringify(body),
    }),
  put: (url, body) => Api.request(url, { method: "PUT", body: JSON.stringify(body) }),
  patch: (url, body) => Api.request(url, { method: "PATCH", body: JSON.stringify(body) }),
  delete: (url) => Api.request(url, { method: "DELETE" }),

  getUsuario() {
    try {
      return JSON.parse(localStorage.getItem("sf_usuario")) || null;
    } catch {
      return null;
    }
  },

  logout() {
    localStorage.removeItem("sf_token");
    localStorage.removeItem("sf_usuario");
    Api.post("/auth/logout", {}).catch(() => {});
    const enHtml = window.location.pathname.includes("/html/");
    window.location.href = enHtml ? "login.html" : "html/login.html";
  },
};

document.addEventListener("DOMContentLoaded", () => {
  const usuario = Api.getUsuario();
  const zonaUsuario = document.getElementById("zona-usuario");
  const navPanel = document.querySelector('a[href*="panel.html"]')?.parentElement;
  const enHtml = window.location.pathname.includes("/html/");

  if (navPanel && (!usuario || (usuario.rol !== "admin" && usuario.rol !== "almacen"))) {
    navPanel.style.display = "none";
  }

  if (zonaUsuario) {
    if (usuario) {
      zonaUsuario.innerHTML = `
        <span class="usuario-badge">${usuario.nombre} (${usuario.rol})</span>
        <button type="button" id="btn-logout" class="btn-limpiar">Salir</button>
      `;
      document.getElementById("btn-logout").addEventListener("click", Api.logout);
    } else {
      zonaUsuario.innerHTML = `<a href="${enHtml ? "login.html" : "html/login.html"}" class="btn">Iniciar Sesión</a>`;
    }
  }
});