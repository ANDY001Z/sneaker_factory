"use strict";

const API_BASE = "/api";

const Api = {
  async request(endpoint, options = {}) {
    const csrfToken = localStorage.getItem("sf_csrf");
    const method = (options.method || "GET").toUpperCase();
    const isWrite = !["GET", "HEAD", "OPTIONS"].includes(method);

    const headers = {
      ...(options.body instanceof FormData ? {} : { "Content-Type": "application/json" }),
      ...(isWrite && csrfToken ? { "X-CSRF-Token": csrfToken } : {}),
      ...(options.headers || {}),
    };

    const res = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      headers,
      credentials: "include",
    });

    const isJson = (res.headers.get("content-type") || "").includes("application/json");
    const raw = isJson ? await res.json() : await res.text();

    if (!res.ok) {
      let msg = `Error HTTP ${res.status}`;
      if (raw && typeof raw === "object") {
        msg = raw.message || raw.error?.message || raw.error || msg;
        const details = raw.details || raw.error?.details;
        if (details && typeof details === "object") {
          const extra = Object.values(details).join(" | ");
          if (extra) msg += `: ${extra}`;
        }
      }
      throw new Error(msg);
    }

    // Desempaquetar si el controlador envuelve la respuesta en { ok: true, data: ... }
    return raw && typeof raw === "object" && "data" in raw && "ok" in raw ? raw.data : raw;
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
    Api.post("/auth/logout", {}).catch(() => {});
    localStorage.removeItem("sf_csrf");
    localStorage.removeItem("sf_token");
    localStorage.removeItem("sf_usuario");
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