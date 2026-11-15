(() => {
  'use strict';

  const form = document.querySelector('#form-seguimiento');
  const inputCodigo = document.querySelector('#codigo');
  const inputEmail = document.querySelector('#email');
  const errorCodigo = document.querySelector('#error-codigo');
  const errorEmail = document.querySelector('#error-email');
  const btnBuscar = document.querySelector('#btn-buscar');
  const aviso = document.querySelector('#estado-busqueda');
  const resultado = document.querySelector('#resultado');

  const ETIQUETA_ESTADO = {
    pendiente: 'Pendiente',
    confirmada: 'Confirmada',
    cancelada: 'Cancelada'
  };

  const formatearEuros = (n) =>
    Number(n).toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });

  const formatearFecha = (iso) =>
    new Date(iso).toLocaleString('es-ES', { dateStyle: 'medium', timeStyle: 'short' });

  function mostrarAviso(tipo, texto) {
    aviso.className = `aviso aviso--${tipo}`;
    aviso.textContent = texto;
    aviso.hidden = false;
  }

  function ocultarAviso() {
    aviso.hidden = true;
    aviso.textContent = '';
  }

  function limpiarResultado() {
    resultado.hidden = true;
    resultado.replaceChildren();
  }

  function validar(codigo, email) {
    let ok = true;
    errorCodigo.textContent = '';
    errorEmail.textContent = '';

    if (!/^RES-[A-Z0-9]+$/.test(codigo)) {
      errorCodigo.textContent = 'Introduce un código válido, por ejemplo RES-A1B2C3.';
      ok = false;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errorEmail.textContent = 'Introduce un correo válido.';
      ok = false;
    }

    return ok;
  }

  async function buscarReserva(codigo, email) {
    const params = new URLSearchParams({ codigo, email });
    const respuesta = await fetch(`/api/reservas/seguimiento?${params}`);
    if (respuesta.status === 404) return null;
    if (!respuesta.ok) {
      const cuerpo = await respuesta.json().catch(() => ({}));
      throw new Error(cuerpo.error || `Error del servidor (${respuesta.status})`);
    }
    return respuesta.json();
  }

  function filaDato(titulo, valor) {
    const dt = document.createElement('dt');
    dt.textContent = titulo;
    const dd = document.createElement('dd');
    dd.textContent = valor;
    const grupo = document.createElement('div');
    grupo.className = 'seg-dato';
    grupo.append(dt, dd);
    return grupo;
  }

  function pintarReserva(r) {
    resultado.replaceChildren();

    const cabecera = document.createElement('div');
    cabecera.className = 'seg-cabecera';

    const titulo = document.createElement('h3');
    titulo.textContent = r.codigo;

    const badge = document.createElement('span');
    badge.className = `seg-badge seg-badge--${r.estado}`;
    badge.textContent = ETIQUETA_ESTADO[r.estado] ?? r.estado;

    cabecera.append(titulo, badge);

    const cuerpo = document.createElement('div');
    cuerpo.className = 'seg-cuerpo';

    const img = document.createElement('img');
    img.className = 'seg-imagen';
    img.src = r.imagen;
    img.alt = `Zapatilla ${r.producto}`;

    const lista = document.createElement('dl');
    lista.className = 'seg-datos';
    lista.append(
      filaDato('Producto', r.producto),
      filaDato('Talla', r.talla),
      filaDato('Cantidad', r.cantidad),
      filaDato('Total', formatearEuros(r.total)),
      filaDato('Fecha', formatearFecha(r.fecha))
    );

    cuerpo.append(img, lista);
    resultado.append(cabecera, cuerpo);
    resultado.hidden = false;
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarResultado();
    ocultarAviso();

    const codigo = inputCodigo.value.trim().toUpperCase();
    const email = inputEmail.value.trim();

    if (!validar(codigo, email)) return;

    btnBuscar.disabled = true;
    mostrarAviso('info', 'Buscando tu reserva...');

    try {
      const reserva = await buscarReserva(codigo, email);
      if (!reserva) {
        mostrarAviso('error', 'No hemos encontrado ninguna reserva con ese código y correo. Revisa los datos.');
        return;
      }
      ocultarAviso();
      pintarReserva(reserva);
    } catch (error) {
      mostrarAviso(
        'error',
        error instanceof TypeError
          ? 'No se pudo conectar con el servidor. Inténtalo de nuevo en unos segundos.'
          : error.message
      );
    } finally {
      btnBuscar.disabled = false;
    }
  });

  // Si vienes desde la confirmación de reserva (?codigo=RES-XXXX), rellena el código automáticamente
  const codigoUrl = new URLSearchParams(window.location.search).get('codigo');
  if (codigoUrl) {
    inputCodigo.value = codigoUrl.toUpperCase();
    inputEmail.focus();
  }
})();