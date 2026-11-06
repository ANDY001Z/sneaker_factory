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

// ---------- Catálogo dinámico ----------
const grid = $(".grid-catalogo");
grid.innerHTML = productos.map(p => `
  <article class="producto">
    <figure>
      <img src="https://placehold.co/300x200?text=${encodeURIComponent(p.nombre)}" alt="Zapatilla ${p.nombre}">
    </figure>
    <h3>${p.nombre}</h3>
    <p class="precio">${euro.format(p.precio)}</p>
    <a href="#reservas" class="btn-sm" data-id="${p.id}">Reservar</a>
  </article>`).join("");

// ---------- Select de modelos ----------
const select = $("#modelo");
select.innerHTML = productos
  .map(p => `<option value="${p.id}">${p.nombre} — ${euro.format(p.precio)}</option>`)
  .join("");

// Al pulsar "Reservar" se preselecciona el modelo en el formulario
grid.addEventListener("click", e => {
  const btn = e.target.closest(".btn-sm");
  if (btn) select.value = btn.dataset.id;
});

// ---------- Formulario ----------
const form = $(".formulario");
const aviso = document.createElement("p");
aviso.setAttribute("role", "status");
form.append(aviso);

const mostrar = (texto, ok) => {
  aviso.textContent = texto;
  aviso.style.color = ok ? "green" : "crimson";
};

form.addEventListener("submit", e => {
  e.preventDefault();
  const { nombre, email, modelo, mensaje } = Object.fromEntries(new FormData(form));

  if (nombre.trim().length < 3) return mostrar("El nombre debe tener al menos 3 caracteres.", false);
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) return mostrar("Introduce un correo válido.", false);

  const producto = productos.find(p => p.id === modelo);
  const reservas = JSON.parse(localStorage.getItem("reservas") || "[]");
  reservas.push({ nombre: nombre.trim(), email, modelo: producto.nombre, mensaje, fecha: new Date().toISOString() });
  localStorage.setItem("reservas", JSON.stringify(reservas));

  mostrar(`¡Gracias, ${nombre.trim()}! Hemos registrado tu solicitud de ${producto.nombre} (${euro.format(producto.precio)}).`, true);
  form.reset();
});

// ---------- Menú: resalta la sección visible (scroll-spy) ----------
const enlaces = [...document.querySelectorAll(".nav a")];
const observer = new IntersectionObserver(entradas => {
  entradas.filter(en => en.isIntersecting).forEach(en => {
    enlaces.forEach(a => a.classList.toggle("activo", a.hash === `#${en.target.id}`));
  });
}, { rootMargin: "-40% 0px -55% 0px" });

document.querySelectorAll("main section[id]").forEach(s => observer.observe(s));