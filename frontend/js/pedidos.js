(() => {
  "use strict";

  const selProducto = document.querySelector("#producto");
  const detalle = document.querySelector("#detalle-producto");
  const contTallas = document.querySelector("#tallas");
  const textoStock = document.querySelector("#texto-stock");
  const inputCantidad = document.querySelector("#cantidad");

  const checkPersonalizar = document.querySelector("#check-personalizar");
  const camposPersonalizacion = document.querySelector("#campos-personalizacion");
  const selectCordones = document.querySelector("#color-cordones");
  const inputBordado = document.querySelector("#texto-bordado");

  const btnAnadirLinea = document.querySelector("#btn-anadir-linea");
  const cuerpoLineas = document.querySelector("#cuerpo-lineas-pedido");
  const direccionEnvioInput = document.querySelector("#direccion-envio");
  const notasPedido = document.querySelector("#notas-pedido");
  const totalPedidoEl = document.querySelector("#total-pedido");
  const btnConfirmarPedido = document.querySelector("#btn-confirmar-pedido");
  const aviso = document.querySelector("#aviso-pedido");
  const confirmacion = document.querySelector("#confirmacion");

  let productoActual = null;
  let lineasPedido = [];

  const formatearEuros = (n) =>
    Number(n).toLocaleString("es-ES", { style: "currency", currency: "EUR" });

  function mostrarAviso(tipo, texto) {
    aviso.className = `aviso aviso--${tipo}`;
    aviso.textContent = texto;
    aviso.hidden = false;
  }

  function ocultarAviso() {
    aviso.hidden = true;
    aviso.textContent = "";
  }

  const usuarioGuardado = Api.getUsuario();
  if (usuarioGuardado?.direccion && direccionEnvioInput) {
    direccionEnvioInput.value = usuarioGuardado.direccion;
  }

  checkPersonalizar.addEventListener("change", () => {
    camposPersonalizacion.classList.toggle("oculto", !checkPersonalizar.checked);
  });

  function pintarSelectorProductos(productos) {
    selProducto.replaceChildren(new Option("Elige un modelo", ""));
    productos.forEach((p) => {
      const agotado = Array.isArray(p.variantes) && p.variantes.every((v) => v.stock === 0);
      const opcion = new Option(
        `${p.marca || ""} ${p.nombre} - ${formatearEuros(p.precio_base)}${agotado ? " (agotado)" : ""}`,
        p.id
      );
      opcion.disabled = agotado;
      selProducto.append(opcion);
    });
  }

  function pintarDetalle() {
    detalle.innerHTML = `
      <img class="detalle-producto_img" src="${productoActual.imagen_url}" alt="${productoActual.nombre}">
      <div class="detalle-producto_info">
        <h4>${productoActual.marca || ""} ${productoActual.nombre}</h4>
        <p class="detalle-producto_categoria">${productoActual.categoria} ${productoActual.personalizable ? "· Personalizable" : ""}</p>
        <p class="detalle-producto_desc">${productoActual.descripcion || ""}</p>
        <p class="precio">${formatearEuros(productoActual.precio_base)}</p>
      </div>
    `;
    detalle.hidden = false;

    checkPersonalizar.checked = false;
    camposPersonalizacion.classList.add("oculto");
    checkPersonalizar.disabled = !productoActual.personalizable;
  }

  function pintarTallas() {
    const variantes = [...(productoActual.variantes || [])].sort((a, b) => Number(a.talla) - Number(b.talla));
    contTallas.innerHTML = variantes
      .map(
        (v) => `
        <label class="${v.stock === 0 ? "talla talla--agotada" : "talla"}">
          <input type="radio" name="variante" value="${v.id}" ${v.stock === 0 ? "disabled" : ""}>
          <span class="talla_caja">
            <strong>${v.talla}</strong>
            <small>${v.color}</small>
            <small>${v.stock > 0 ? `${v.stock} uds.` : "Agotada"}</small>
          </span>
        </label>
      `
      )
      .join("");
  }

  function varianteSeleccionada() {
    const marcada = contTallas.querySelector('input[name="variante"]:checked');
    if (!marcada) return null;
    return productoActual?.variantes?.find((item) => String(item.id) === String(marcada.value)) || null;
  }

  async function seleccionarProducto(id) {
    ocultarAviso();
    if (!id) {
      productoActual = null;
      detalle.hidden = true;
      contTallas.innerHTML = "";
      textoStock.textContent = "Elige un modelo para ver las tallas.";
      return;
    }

    try {
      productoActual = await Api.get(`/modelos/${encodeURIComponent(id)}`);
      pintarDetalle();
      pintarTallas();
      textoStock.textContent = productoActual.personalizable
        ? "Selecciona una talla y color (este modelo admite personalización)."
        : "Selecciona una talla y color (este modelo no admite personalización).";
    } catch (err) {
      mostrarAviso("error", err.message);
    }
  }

  contTallas.addEventListener("change", () => {
    const v = varianteSeleccionada();
    if (!v) return;
    textoStock.textContent = `Quedan ${v.stock} unidades en talla ${v.talla} (${v.color}).`;
    inputCantidad.max = String(Math.max(v.stock, 1));
  });

  btnAnadirLinea.addEventListener("click", () => {
    ocultarAviso();
    const v = varianteSeleccionada();
    const cantidad = Number(inputCantidad.value);

    if (!productoActual) return mostrarAviso("error", "Selecciona un modelo de zapatillas.");
    if (!v) return mostrarAviso("error", "Selecciona una talla y color.");
    if (!Number.isInteger(cantidad) || cantidad < 1) return mostrarAviso("error", "Cantidad inválida.");

    const yaEnCarrito = lineasPedido
      .filter((l) => l.variante_id === v.id)
      .reduce((acc, l) => acc + l.cantidad, 0);

    if (cantidad + yaEnCarrito > v.stock) {
      return mostrarAviso("error", `Stock insuficiente en talla ${v.talla} (${v.color}) — máx. ${v.stock} uds.`);
    }

    const personalizada = Boolean(productoActual.personalizable && checkPersonalizar.checked);
    const precioUnitario = Number(productoActual.precio_base);

    lineasPedido.push({
      variante_id: v.id,
      nombre: `${productoActual.marca || ""} ${productoActual.nombre}`.trim(),
      talla: `${v.talla} (${v.color})`,
      cantidad,
      precio_unitario: precioUnitario,
      personalizada,
      opciones_personalizacion: personalizada
        ? {
            color_cordones: selectCordones.value,
            texto_bordado: inputBordado.value.trim(),
          }
        : null,
    });

    renderizarLineas();
    inputCantidad.value = 1;
    checkPersonalizar.checked = false;
    camposPersonalizacion.classList.add("oculto");
    inputBordado.value = "";
  });

  function renderizarLineas() {
    if (lineasPedido.length === 0) {
      cuerpoLineas.innerHTML = `<tr><td colspan="6" style="text-align:center; color:#666;">Aún no has añadido zapatillas.</td></tr>`;
      totalPedidoEl.textContent = formatearEuros(0);
      btnConfirmarPedido.disabled = true;
      return;
    }

    let total = 0;
    cuerpoLineas.innerHTML = lineasPedido
      .map((linea, index) => {
        const subtotal = linea.precio_unitario * linea.cantidad;
        total += subtotal;
        const extra = linea.personalizada
          ? `Pers. (${linea.opciones_personalizacion.color_cordones}${linea.opciones_personalizacion.texto_bordado ? ` / "${linea.opciones_personalizacion.texto_bordado}"` : ""})`
          : "Estándar";
        return `
          <tr>
            <td>${linea.nombre}</td>
            <td>${linea.talla}</td>
            <td><small>${extra}</small></td>
            <td>${linea.cantidad}</td>
            <td>${formatearEuros(subtotal)}</td>
            <td><button type="button" class="btn-limpiar" data-quitar="${index}" style="height:28px; padding:0 8px;">X</button></td>
          </tr>
        `;
      })
      .join("");

    totalPedidoEl.textContent = formatearEuros(total);
    btnConfirmarPedido.disabled = false;
  }

  cuerpoLineas.addEventListener("click", (e) => {
    if (e.target.dataset.quitar !== undefined) {
      lineasPedido.splice(Number(e.target.dataset.quitar), 1);
      renderizarLineas();
    }
  });

  btnConfirmarPedido.addEventListener("click", async () => {
    const usuario = Api.getUsuario();
    if (!usuario) {
      mostrarAviso("error", "Debes iniciar sesión como cliente para confirmar tu pedido.");
      setTimeout(() => (window.location.href = "login.html"), 1500);
      return;
    }

    const direccionEnvio = direccionEnvioInput ? direccionEnvioInput.value.trim() : "";
    if (direccionEnvio.length < 5) {
      mostrarAviso("error", "Introduce una dirección de envío válida (mínimo 5 caracteres).");
      return;
    }

    btnConfirmarPedido.disabled = true;
    btnConfirmarPedido.textContent = "Tramitando pedido...";

    try {
      const res = await Api.post("/pedidos", {
        direccion_envio: direccionEnvio,
        notas: notasPedido.value.trim() || null,
        lineas: lineasPedido.map((l) => ({
          variante_id: l.variante_id,
          cantidad: l.cantidad,
          opciones_personalizacion: l.opciones_personalizacion,
        })),
      });

      const pedido = res.pedido || res;
      lineasPedido = [];
      renderizarLineas();
      confirmacion.innerHTML = `
        <h3>¡Pedido #${pedido.codigo_pedido || pedido.id || "OK"} registrado!</h3>
        <p class="subtitulo-form">Hemos reservado tu stock y guardado los detalles del pedido.</p>
        <div class="confirmacion_acciones">
          <a href="mis-pedidos.html" class="btn">Ver Mis Pedidos</a>
        </div>
      `;
      confirmacion.hidden = false;
      if (productoActual) seleccionarProducto(productoActual.id);
    } catch (err) {
      mostrarAviso("error", err.message);
    } finally {
      btnConfirmarPedido.textContent = "Confirmar Pedido";
      btnConfirmarPedido.disabled = lineasPedido.length === 0;
    }
  });

  async function iniciar() {
    try {
      const datos = await Api.get("/modelos?limit=50");
      const lista = Array.isArray(datos) ? datos : datos.items || [];
      pintarSelectorProductos(lista);
      const idUrl = new URLSearchParams(window.location.search).get("modelo");
      if (idUrl) {
        selProducto.value = idUrl;
        await seleccionarProducto(idUrl);
      }
    } catch (err) {
      mostrarAviso("error", err.message);
    }
  }

  selProducto.addEventListener("change", () => seleccionarProducto(selProducto.value));
  iniciar();
})();