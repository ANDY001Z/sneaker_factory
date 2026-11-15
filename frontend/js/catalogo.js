"use strict";

// Referencias al DOM del Catálogo y Filtros
const gridCatalogo = document.getElementById("grid-catalogo");
const loaderCatalogo = document.getElementById("loader-catalogo");
const mensajeCatalogo = document.getElementById("mensaje-catalogo");
const contadorResultados = document.getElementById("contador-resultados");

const inputBusqueda = document.getElementById("filtro-busqueda");
const selectCategoria = document.getElementById("filtro-categoria");
const selectTalla = document.getElementById("filtro-talla");
const selectOrden = document.getElementById("filtro-orden");
const btnLimpiarFiltros = document.getElementById("btn-limpiar-filtros");

// Referencias al DOM del Formulario de Consultas Comerciales
const formConsulta = document.getElementById("form-consulta");
const feedbackConsulta = document.getElementById("mensaje-feedback-consulta");

// Formateador de moneda en Euros
const formatearEuro = new Intl.NumberFormat("es-ES", {
  style: "currency",
  currency: "EUR",
});

// Estado en memoria de los productos recibidos de la API
let productosAPI = [];

// Control visual del loader y mensajes de estado del catálogo
function mostrarLoader(visible) {
  if (loaderCatalogo) {
    loaderCatalogo.classList.toggle("oculto", !visible);
  }
}

function mostrarMensaje(texto, tipo = "vacio") {
  if (mensajeCatalogo) {
    mensajeCatalogo.textContent = texto;
    mensajeCatalogo.className = `mensaje-estado ${tipo}`;
  }
}

function ocultarMensaje() {
  if (mensajeCatalogo) {
    mensajeCatalogo.className = "mensaje-estado oculto";
    mensajeCatalogo.textContent = "";
  }
}

// Rellena dinámicamente el <select> de categorías a partir del JSON recibido
function cargarCategoriasEnSelect(productos) {
  if (!selectCategoria) return;
  const categoriasUnicas = [...new Set(productos.map(p => p.categoria).filter(Boolean))];
  selectCategoria.innerHTML =
    '<option value="">Todas las categorías</option>' +
    categoriasUnicas.map(cat => `<option value="${cat}">${cat}</option>`).join("");
}

// Calcula el stock total disponible de un producto (soporta variantes o campo stock directo)
function obtenerStockDisponible(producto) {
  if (Array.isArray(producto.variantes)) {
    return producto.variantes.reduce((acc, v) => acc + Number(v.stock || 0), 0);
  }
  if (producto.stock_total !== undefined) {
    return Number(producto.stock_total);
  }
  return null;
}

// Renderiza las tarjetas HTML en #grid-catalogo iterando sobre el array recibido
function renderizarCatalogo(listaProductos) {
  if (!gridCatalogo) return;
  gridCatalogo.innerHTML = "";

  if (!listaProductos.length) {
    if (contadorResultados) contadorResultados.textContent = "0 modelos encontrados";
    mostrarMensaje("No hay zapatillas que coincidan con los filtros seleccionados.", "vacio");
    return;
  }

  ocultarMensaje();
  if (contadorResultados) {
    contadorResultados.textContent = `Mostrando ${listaProductos.length} ${listaProductos.length === 1 ? "modelo" : "modelos"}`;
  }

  listaProductos.forEach(producto => {
    const tarjeta = document.createElement("article");
    tarjeta.className = "producto";

    const imagenUrl = producto.imagen
      ? producto.imagen
      : `https://placehold.co/400x260/181818/ffffff?text=${encodeURIComponent(producto.nombre)}`;

    const stockTotal = obtenerStockDisponible(producto);
    const hayStock = stockTotal === null || stockTotal > 0;
    const textoStock =
      stockTotal === null
        ? "Disponible en tienda"
        : hayStock
          ? `En stock (${stockTotal} uds.)`
          : "Agotado temporalmente";

    tarjeta.innerHTML = `
      <div>
        <figure>
          <img src="${imagenUrl}" alt="Zapatilla ${producto.nombre}" loading="lazy">
        </figure>
        <span class="etiqueta-categoria">${producto.categoria || "Sneakers"}</span>
        <h3>${producto.nombre}</h3>
        <p class="producto-desc">${producto.descripcion || "Diseño exclusivo Sneaker Factory."}</p>
      </div>
      <div>
        <p class="producto-stock ${hayStock ? "stock-ok" : "stock-agotado"}">${textoStock}</p>
        <div class="producto-pie">
          <p class="precio">${formatearEuro.format(Number(producto.precio))}</p>
          <a href="html/reservas.html?modelo=${encodeURIComponent(producto.id)}" class="btn-sm">
            ${hayStock ? "Reservar" : "Consultar"}
          </a>
        </div>
      </div>
    `;

    gridCatalogo.appendChild(tarjeta);
  });
}

// Aplica búsqueda, categoría, talla y ordenación sin recargar la página
function aplicarFiltrosYOrden() {
  const texto = inputBusqueda ? inputBusqueda.value.trim().toLowerCase() : "";
  const categoria = selectCategoria ? selectCategoria.value : "";
  const talla = selectTalla ? selectTalla.value : "";
  const orden = selectOrden ? selectOrden.value : "";

  let filtrados = productosAPI.filter(producto => {
    const coincideTexto =
      !texto ||
      producto.nombre.toLowerCase().includes(texto) ||
      (producto.descripcion && producto.descripcion.toLowerCase().includes(texto));

    const coincideCategoria = !categoria || producto.categoria === categoria;

    let coincideTalla = true;
    if (talla) {
      if (Array.isArray(producto.variantes)) {
        coincideTalla = producto.variantes.some(
          v => String(v.talla) === String(talla) && Number(v.stock) > 0
        );
      } else if (Array.isArray(producto.tallas)) {
        coincideTalla = producto.tallas.map(String).includes(String(talla));
      }
    }

    return coincideTexto && coincideCategoria && coincideTalla;
  });

  if (orden === "precio-asc") {
    filtrados.sort((a, b) => Number(a.precio) - Number(b.precio));
  } else if (orden === "precio-desc") {
    filtrados.sort((a, b) => Number(b.precio) - Number(a.precio));
  }

  renderizarCatalogo(filtrados);
}

// Petición inicial GET /api/productos al cargar la página
async function cargarProductosDesdeAPI() {
  mostrarLoader(true);
  ocultarMensaje();
  if (gridCatalogo) gridCatalogo.innerHTML = "";
  if (contadorResultados) contadorResultados.textContent = "Conectando con el catálogo...";

  try {
    const respuesta = await fetch("/api/productos");

    if (!respuesta.ok) {
      throw new Error(`Error del servidor (${respuesta.status})`);
    }

    const datos = await respuesta.json();
    const lista = Array.isArray(datos) ? datos : datos.productos || datos.data || [];

    productosAPI = lista.filter(p => p.activo !== false);
    cargarCategoriasEnSelect(productosAPI);
    renderizarCatalogo(productosAPI);
  } catch (error) {
    console.error("Error al cargar /api/productos:", error);
    if (contadorResultados) contadorResultados.textContent = "Error de conexión";
    mostrarMensaje(
      "No se pudieron cargar los productos desde la API. Comprueba que el servidor Express y PostgreSQL estén activos.",
      "error"
    );
  } finally {
    mostrarLoader(false);
  }
}

// Configuración de eventos para los filtros y limpieza
if (inputBusqueda) inputBusqueda.addEventListener("input", aplicarFiltrosYOrden);
if (selectCategoria) selectCategoria.addEventListener("change", aplicarFiltrosYOrden);
if (selectTalla) selectTalla.addEventListener("change", aplicarFiltrosYOrden);
if (selectOrden) selectOrden.addEventListener("change", aplicarFiltrosYOrden);

if (btnLimpiarFiltros) {
  btnLimpiarFiltros.addEventListener("click", () => {
    if (inputBusqueda) inputBusqueda.value = "";
    if (selectCategoria) selectCategoria.value = "";
    if (selectTalla) selectTalla.value = "";
    if (selectOrden) selectOrden.value = "";
    renderizarCatalogo(productosAPI);
  });
}

// Lógica del Formulario de Consultas Comerciales (POST /api/consultas)
if (formConsulta) {
  formConsulta.addEventListener("submit", async (e) => {
    e.preventDefault();

    const nuevaConsulta = {
      nombre: document.getElementById("nombre-consulta").value.trim(),
      email: document.getElementById("email-consulta").value.trim(),
      asunto: document.getElementById("asunto-consulta").value.trim(),
      mensaje: document.getElementById("mensaje-consulta").value.trim()
    };

    try {
      if (feedbackConsulta) {
        feedbackConsulta.textContent = "Enviando consulta...";
        feedbackConsulta.className = "mensaje-estado cargando";
      }

      const respuesta = await fetch("/api/consultas", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(nuevaConsulta)
      });

      const resultado = await respuesta.json();

      if (!respuesta.ok) {
        throw new Error(resultado.error || "No se ha podido registrar la consulta.");
      }

      if (feedbackConsulta) {
        feedbackConsulta.textContent = "¡Consulta enviada con éxito! Nos pondremos en contacto pronto.";
        feedbackConsulta.className = "mensaje-estado exito";
      }
      
      formConsulta.reset();
    } catch (error) {
      console.error("Error al enviar consulta:", error);
      if (feedbackConsulta) {
        feedbackConsulta.textContent = error.message;
        feedbackConsulta.className = "mensaje-estado error";
      }
    }
  });
}

// Inicialización al cargar el DOM
document.addEventListener("DOMContentLoaded", cargarProductosDesdeAPI);
