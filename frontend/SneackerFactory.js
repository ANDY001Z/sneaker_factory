"use strict";

// ---------- Datos ----------
const productos = [
  { id: "urban",  nombre: "Urban Runner X",  precio: 129.99 },
  { id: "retro",  nombre: "Retro High '85",  precio: 149.90 },
  { id: "street", nombre: "Street Boost",    precio: 119 },
  { id: "air",    nombre: "Air Classic Pro", precio: 179.50 },
];

const euro = new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" });
const $ = (sel, ctx = document) => ctx.querySelector(sel);

// ---------- Catálogo dinámico (solo si estamos en index.html) ----------
const grid = $(".grid-catalogo");
if (grid) {
  grid.innerHTML = productos.map(p => `
    <article class="producto">
      <figure>
        <img src="https://placehold.co/300x200?text=${encodeURIComponent(p.nombre)}" alt="Zapatilla ${p.nombre}">
      </figure>
      <h3>${p.nombre}</h3>
      <p class="precio">${euro.format(p.precio)}</p>
      <a href="reservas.html?modelo=${p.id}" class="btn-sm">Reservar</a>
    </article>`).join("");
}

// ---------- Formulario de Reservas (solo si estamos en reservas.html) ----------
const select = $("#modelo");
const form = $(".formulario");

if (select && form) {
  select.innerHTML = productos
    .map(p => `<option value="${p.id}">${p.nombre} — ${euro.format(p.precio)}</option>`)
    .join("");

  // Leer el parámetro ?modelo= de la URL al entrar desde el catálogo
  const params = new URLSearchParams(window.location.search);
  const modeloURL = params.get("modelo");
  if (modeloURL && productos.some(p => p.id === modeloURL)) {
    select.value = modeloURL;
  }

  const aviso = document.createElement("p");
  aviso.setAttribute("role", "status");
  aviso.style.marginTop = "12px";
  aviso.style.fontWeight = "bold";
  form.append(aviso);

  const mostrar = (texto, ok) => {
    aviso.textContent = texto;
    aviso.style.color = ok ? "green" : "crimson";
  };

  form.addEventListener("submit", e => {
    e.preventDefault();
    const { nombre, email, modelo, talla, mensaje } = Object.fromEntries(new FormData(form));

    if (nombre.trim().length < 3) return mostrar("El nombre debe tener al menos 3 caracteres.", false);
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) return mostrar("Introduce un correo válido.", false);

    const producto = productos.find(p => p.id === modelo);
    const reservas = JSON.parse(localStorage.getItem("reservas") || "[]");
    reservas.push({
      nombre: nombre.trim(),
      email,
      modelo: producto.nombre,
      talla: talla || "42",
      mensaje,
      fecha: new Date().toISOString()
    });
    localStorage.setItem("reservas", JSON.stringify(reservas));

    mostrar(`¡Gracias, ${nombre.trim()}! Hemos registrado tu solicitud de ${producto.nombre} (Talla ${talla} EU — ${euro.format(producto.precio)}).`, true);
    form.reset();
  });
}

// ---------- Menú: resalta la sección visible (scroll-spy en index.html) ----------
const enlaces = [...document.querySelectorAll(".nav a")];
const secciones = document.querySelectorAll("main section[id]");

if (secciones.length > 1) {
  const observer = new IntersectionObserver(entradas => {
    entradas.filter(en => en.isIntersecting).forEach(en => {
      enlaces.forEach(a => {
        if (a.hash) a.classList.toggle("activo", a.hash === `#${en.target.id}`);
      });
    });
  }, { rootMargin: "-40% 0px -55% 0px" });

  secciones.forEach(s => observer.observe(s));
}