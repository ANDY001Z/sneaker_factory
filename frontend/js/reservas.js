(() => {
  'use strict';

  const API = '/api';

  // Referencias al DOM
  const form = document.querySelector('#form-reserva');
  const selProducto = document.querySelector('#producto');
  const detalle = document.querySelector('#detalle-producto');
  const contTallas = document.querySelector('#tallas');
  const textoStock = document.querySelector('#texto-stock');
  const inputCantidad = document.querySelector('#cantidad');
  const inputNombre = document.querySelector('#nombre');
  const inputEmail = document.querySelector('#email');
  const totalTexto = document.querySelector('#total-estimado');
  const btnReservar = document.querySelector('#btn-reservar');
  const aviso = document.querySelector('#aviso-reserva');
  const confirmacion = document.querySelector('#confirmacion');

  const errores = {
    producto: document.querySelector('#error-producto'),
    talla: document.querySelector('#error-talla'),
    cantidad: document.querySelector('#error-cantidad'),
    nombre: document.querySelector('#error-nombre'),
    email: document.querySelector('#error-email')
  };

  let producto = null; // producto elegido, con variantes y stock actualizado

  // Utilidades
  const formatearEuros = (n) =>
    Number(n).toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });

  const mensajeDeError = (e) =>
    e instanceof TypeError ? 'No se pudo conectar con el servidor.' : e.message;

  function crear(etiqueta, clase, texto) {
    const nodo = document.createElement(etiqueta);
    if (clase) nodo.className = clase;
    if (texto !== undefined) nodo.textContent = texto;
    return nodo;
  }

  function mostrarAviso(tipo, texto) {
    aviso.className = `aviso aviso--${tipo}`;
    aviso.textContent = texto;
    aviso.hidden = false;
  }

  function ocultarAviso() {
    aviso.hidden = true;
    aviso.textContent = '';
  }

  function limpiarErrores() {
    for (const nodo of Object.values(errores)) nodo.textContent = '';
    form.querySelectorAll('.campo--invalido').forEach((c) => c.classList.remove('campo--invalido'));
  }

  // Acceso a la API
  async function cargarProductos() {
    const respuesta = await fetch(`${API}/productos`);
    if (!respuesta.ok) throw new Error('No se pudo cargar el catálogo.');
    return respuesta.json();
  }

  async function cargarProducto(id) {
    const respuesta = await fetch(`${API}/productos/${encodeURIComponent(id)}`);
    if (respuesta.status === 404) throw new Error('Este producto ya no está disponible.');
    if (!respuesta.ok) throw new Error('No se pudo cargar el producto.');
    return respuesta.json();
  }

  // Pintar la interfaz
  function pintarSelectorProductos(productos) {
    selProducto.replaceChildren(new Option('Elige un modelo', ''));
    productos.forEach((p) => {
      const agotado = p.variantes.every((v) => v.stock === 0);
      const sufijo = agotado ? ' (agotado)' : '';
      const opcion = new Option(`${p.marca} ${p.nombre} - ${formatearEuros(p.precio)}${sufijo}`, p.id);
      opcion.disabled = agotado;
      selProducto.append(opcion);
    });
  }

  function pintarDetalle() {
    const img = crear('img', 'detalle-producto_img');
    img.src = producto.imagen;
    img.alt = `${producto.marca} ${producto.nombre}`;

    const info = crear('div', 'detalle-producto_info');
    info.append(
      crear('h4', '', `${producto.marca} ${producto.nombre}`),
      crear('p', 'detalle-producto_categoria', producto.categoria),
      crear('p', 'detalle-producto_desc', producto.descripcion),
      crear('p', 'precio', formatearEuros(producto.precio))
    );

    detalle.replaceChildren(img, info);
    detalle.hidden = false;
  }

  function crearBotonTalla(talla, stock, marcada) {
    const input = crear('input');
    input.type = 'radio';
    input.name = 'talla';
    input.value = talla;
    input.disabled = stock === 0;
    input.checked = marcada;

    const caja = crear('span', 'talla_caja');
    caja.append(crear('strong', '', talla), crear('small', '', stock > 0 ? `${stock} uds.` : 'Agotada'));

    const etiqueta = crear('label', stock === 0 ? 'talla talla--agotada' : 'talla');
    etiqueta.append(input, caja);
    return etiqueta;
  }

  function pintarTallas(tallaElegida) {
    const botones = [...producto.variantes]
      .sort((a, b) => Number(a.talla) - Number(b.talla))
      .map(({ talla, stock }) => crearBotonTalla(talla, stock, talla === tallaElegida && stock > 0));
    contTallas.replaceChildren(...botones);
  }

  function tallaSeleccionada() {
    const marcada = contTallas.querySelector('input[name="talla"]:checked');
    return marcada ? marcada.value : '';
  }

  function stockSeleccionado() {
    const variante = producto?.variantes.find((v) => v.talla === tallaSeleccionada());
    return variante ? variante.stock : 0;
  }

  function actualizarResumen() {
    if (!producto) {
      textoStock.textContent = 'Elige un modelo para ver las tallas disponibles.';
      totalTexto.textContent = formatearEuros(0);
      return;
    }

    const talla = tallaSeleccionada();
    const stock = stockSeleccionado();
    const cantidad = Number(inputCantidad.value);

    textoStock.textContent = talla
      ? `Quedan ${stock} unidades en talla ${talla}.`
      : 'Selecciona una talla para ver cuántas unidades quedan.';

    inputCantidad.max = String(Math.max(stock, 1));
    const valida = Number.isInteger(cantidad) && cantidad > 0;
    totalTexto.textContent = formatearEuros(valida ? Number(producto.precio) * cantidad : 0);
  }

  async function refrescarProducto(tallaElegida) {
    producto = await cargarProducto(producto.id);
    pintarTallas(tallaElegida);
    actualizarResumen();
  }

  async function seleccionarProducto(id) {
    limpiarErrores();
    ocultarAviso();

    if (!id) {
      producto = null;
      detalle.hidden = true;
      contTallas.replaceChildren();
      btnReservar.disabled = true;
      actualizarResumen();
      return;
    }

    mostrarAviso('info', 'Cargando disponibilidad...');
    try {
      const datos = await cargarProducto(id);
      if (selProducto.value !== String(id)) return; // el usuario cambió de modelo mientras cargaba
      producto = datos;
      pintarDetalle();
      pintarTallas();
      actualizarResumen();
      ocultarAviso();
      btnReservar.disabled = false;
    } catch (error) {
      producto = null;
      btnReservar.disabled = true;
      mostrarAviso('error', mensajeDeError(error));
    }
  }

  // Validación
  function validar() {
    const fallos = {};
    const talla = tallaSeleccionada();
    const stock = stockSeleccionado();
    const cantidad = Number(inputCantidad.value);

    if (!producto) fallos.producto = 'Elige un modelo.';
    if (!talla) fallos.talla = 'Elige una talla.';

    if (!Number.isInteger(cantidad) || cantidad < 1) {
      fallos.cantidad = 'La cantidad debe ser un número entero de al menos 1.';
    } else if (talla && cantidad > stock) {
      fallos.cantidad = `Solo quedan ${stock} unidades de esta talla.`;
    }

    if (inputNombre.value.trim().length < 2) {
      fallos.nombre = 'Escribe tu nombre (mínimo 2 caracteres).';
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(inputEmail.value.trim())) {
      fallos.email = 'Introduce un correo válido.';
    }

    for (const [clave, nodo] of Object.entries(errores)) {
      nodo.textContent = fallos[clave] ?? '';
      nodo.parentElement.classList.toggle('campo--invalido', Boolean(fallos[clave]));
    }

    return Object.keys(fallos).length === 0;
  }

  // Envío
  async function enviarReserva(evento) {
    evento.preventDefault();
    ocultarAviso();
    if (!validar()) return;

    const datos = {
      producto_id: producto.id,
      talla: tallaSeleccionada(),
      cantidad: Number(inputCantidad.value),
      nombre_cliente: inputNombre.value.trim(),
      email: inputEmail.value.trim()
    };

    btnReservar.disabled = true;
    btnReservar.textContent = 'Reservando...';

    try {
      const respuesta = await fetch(`${API}/reservas`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
      });

      const cuerpo = await respuesta.json().catch(() => ({}));

      if (respuesta.status === 409) {
        mostrarAviso('error', 'Stock insuficiente: alguien se te ha adelantado. Hemos actualizado las tallas.');
        await refrescarProducto(datos.talla);
        return;
      }

      if (!respuesta.ok) throw new Error(cuerpo.error || `Error del servidor (${respuesta.status})`);

      mostrarConfirmacion(cuerpo.reserva);
      refrescarProducto().catch(() => {}); // recalcula el stock en segundo plano
    } catch (error) {
      mostrarAviso('error', mensajeDeError(error));
    } finally {
      btnReservar.disabled = false;
      btnReservar.textContent = 'Confirmar reserva';
    }
  }

  function mostrarConfirmacion(reserva) {
    const total = formatearEuros(reserva.total);
    const detalleTexto = `Talla ${reserva.talla} · ${reserva.cantidad} ud. · Total ${total}`;

    const enlace = crear('a', 'btn', 'Ver seguimiento');
    enlace.href = `seguimiento.html?codigo=${encodeURIComponent(reserva.codigo)}`;

    const otra = crear('button', 'btn btn-secundario', 'Hacer otra reserva');
    otra.type = 'button';
    otra.addEventListener('click', nuevaReserva);

    const acciones = crear('div', 'confirmacion_acciones');
    acciones.append(enlace, otra);

    confirmacion.replaceChildren(
      crear('h3', '', '¡Reserva registrada!'),
      crear('p', 'confirmacion_codigo', reserva.codigo),
      crear('p', '', detalleTexto),
      crear('p', 'subtitulo-form', 'Guarda este código y el correo que has usado para consultar tu reserva.'),
      acciones
    );

    confirmacion.hidden = false;
    form.hidden = true;
  }

  function nuevaReserva() {
    confirmacion.hidden = true;
    form.hidden = false;
    inputCantidad.value = 1;
    limpiarErrores();
    actualizarResumen();
  }

  // Arranque
  async function iniciar() {
    btnReservar.disabled = true;
    mostrarAviso('info', 'Cargando catálogo...');

    try {
      const productos = await cargarProductos();
      if (productos.length === 0) {
        mostrarAviso('info', 'Ahora mismo no hay zapatillas disponibles para reservar.');
        selProducto.replaceChildren(new Option('Sin modelos disponibles', ''));
        return;
      }

      pintarSelectorProductos(productos);
      ocultarAviso();

      const paramsUrl = new URLSearchParams(window.location.search);
      const idUrl = paramsUrl.get('producto') || paramsUrl.get('modelo');
      if (idUrl && productos.some((p) => String(p.id) === idUrl && p.variantes.some((v) => v.stock > 0))) {
        selProducto.value = idUrl;
        await seleccionarProducto(idUrl);
      }
    } catch (error) {
      selProducto.replaceChildren(new Option('No se pudo cargar el catálogo', ''));
      mostrarAviso('error', mensajeDeError(error));
    }
  }

  selProducto.addEventListener('change', () => seleccionarProducto(selProducto.value));
  contTallas.addEventListener('change', actualizarResumen);
  inputCantidad.addEventListener('input', actualizarResumen);
  form.addEventListener('submit', enviarReserva);

  iniciar();
})();