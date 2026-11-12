"use strict";

// ---------- Datos (Se cargan desde PostgreSQL) ----------
let productos = [];

const euro = new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" });
const $ = (sel, ctx = document) => ctx.querySelector(sel);

const grid = $(".grid-catalogo");
const select = $("#modelo");

// ---------- Catálogo dinámico desde Backend ----------
async function cargarCatalogo() {
  try {
    // Nota: si en nginx/default.conf tienes prefijo /api/, cambia "/productos" por "/api/productos"
    const res = await fetch("/productos");
    productos = await res.json();

    grid.innerHTML = productos.map(p => `
      <article class="producto">
        <figure>
          <img src="https://placehold.co/300x200?text=${encodeURIComponent(p.name)}" alt="Zapatilla ${p.name}">
        </figure>
        <h3>${p.name}</h3>
        <p class="precio">${euro.format(p.price)}</p>
        <a href="#reservas" class="btn-sm" data-id="${p.id}">Reservar</a>
      </article>`).join("");

    // ---------- Select de modelos ----------
    select.innerHTML = productos
      .map(p => `<option value="${p.id}">${p.name} — ${euro.format(p.price)}</option>`)
      .join("");
  } catch (err) {
    console.error("Error al cargar el catálogo desde la API:", err);
  }
}

cargarCatalogo();

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

  const producto = productos.find(p => String(p.id) === String(modelo));
  if (!producto) return mostrar("Selecciona un modelo válido.", false);

  const reservas = JSON.parse(localStorage.getItem("reservas") || "[]");
  reservas.push({ nombre: nombre.trim(), email, modelo: producto.name, mensaje, fecha: new Date().toISOString() });
  localStorage.setItem("reservas", JSON.stringify(reservas));

  mostrar(`¡Gracias, ${nombre.trim()}! Hemos registrado tu solicitud de ${producto.name} (${euro.format(producto.price)}).`, true);
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