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
  const notasPedido = document.querySelector("#notas-pedido");
  const totalPedidoEl = document.querySelector("#total-pedido");
  const btnConfirmarPedido = document.querySelector("#btn-confirmar-pedido");
  const aviso = document.querySelector("#aviso-pedido");
  const confirmacion = document.querySelector("#confirmacion");

  let productoActual = null;
  let lineasPedido = [];
  const SUPLEMENTO_PERSONALIZACION = 15.0;

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

  checkPersonalizar.addEventListener("change", () => {
    camposPersonalizacion.classList.toggle("oculto", !checkPersonalizar.checked);
  });

  function pintarSelectorProductos(productos) {
    selProducto.replaceChildren(new Option("Elige un modelo", ""));
    productos.forEach((p) => {
      const agotado = Array.isArray(p.variantes) && p.variantes.every((v) => v.stock === 0);
      const opcion = new Option(
        `${p.marca || ""} ${p.nombre} - ${formatearEuros(p.precio)}${agotado ? " (agotado)" : ""}`,
        p.id
      );
      opcion.disabled = agotado;
      selProducto.append(opcion);
    });
  }

  function pintarDetalle() {
    detalle.innerHTML = `
      <img class="detalle-producto_img" src="${productoActual.imagen}" alt="${productoActual.nombre}">
      <div class="detalle-producto_info">
        <h4>${productoActual.marca || ""} ${productoActual.nombre}</h4>
        <p class="detalle-producto_categoria">${productoActual.categoria}</p>
        <p class="detalle-producto_desc">${productoActual.descripcion || ""}</p>
        <p class="precio">${formatearEuros(productoActual.precio)}</p>
      </div>
    `;
    detalle.hidden = false;
  }

  function pintarTallas() {
    const variantes = [...(productoActual.variantes || [])].sort((a, b) => Number(a.talla) - Number(b.talla));
    contTallas.innerHTML = variantes
      .map(
        ({ talla, stock }) => `
        <label class="${stock === 0 ? "talla talla--agotada" : "talla"}">
          <input type="radio" name="talla" value="${talla}" ${stock === 0 ? "disabled" : ""}>
          <span class="talla_caja">
            <strong>${talla}</strong>
            <small>${stock > 0 ? `${stock} uds.` : "Agotada"}</small>
          </span>
        </label>
      `
      )
      .join("");
  }

  function tallaSeleccionada() {
    const marcada = contTallas.querySelector('input[name="talla"]:checked');
    return marcada ? marcada.value : "";
  }

  function stockSeleccionado() {
    const v = productoActual?.variantes?.find((item) => String(item.talla) === String(tallaSeleccionada()));
    return v ? Number(v.stock) : 0;
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
      productoActual = await Api.get(`/productos/${encodeURIComponent(id)}`);
      pintarDetalle();
      pintarTallas();
      textoStock.textContent = "Selecciona una talla para ver el stock disponible.";
    } catch (err) {
      mostrarAviso("error", err.message);
    }
  }

  contTallas.addEventListener("change", () => {
    const talla = tallaSeleccionada();
    const stock = stockSeleccionado();
    textoStock.textContent = `Quedan ${stock} unidades en talla ${talla}.`;
    inputCantidad.max = String(Math.max(stock, 1));
  });

  // Añadir línea al carrito del pedido
  btnAnadirLinea.addEventListener("click", () => {
    ocultarAviso();
    const talla = tallaSeleccionada();
    const stock = stockSeleccionado();
    const cantidad = Number(inputCantidad.value);

    if (!productoActual) return mostrarAviso("error", "Selecciona un modelo de zapatillas.");
    if (!talla) return mostrarAviso("error", "Selecciona una talla.");
    if (!Number.isInteger(cantidad) || cantidad < 1) return mostrarAviso("error", "Cantidad inválida.");

    const yaEnCarrito = lineasPedido
      .filter((l) => l.producto_id === productoActual.id && l.talla === talla)
      .reduce((acc, l) => acc + l.cantidad, 0);

    if (cantidad + yaEnCarrito > stock) {
      return mostrarAviso("error", `Stock insuficiente en talla ${talla} (máx. ${stock} uds.).`);
    }

    const personalizada = checkPersonalizar.checked;
    const precioUnitario = Number(productoActual.precio) + (personalizada ? SUPLEMENTO_PERSONALIZACION : 0);

    lineasPedido.push({
      producto_id: productoActual.id,
      nombre: `${productoActual.marca || ""} ${productoActual.nombre}`.trim(),
      talla,
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

  // Enviar pedido completo al servidor
  btnConfirmarPedido.addEventListener("click", async () => {
    const usuario = Api.getUsuario();
    if (!usuario) {
      mostrarAviso("error", "Debes iniciar sesión como cliente para confirmar tu pedido.");
      setTimeout(() => (window.location.href = "login.html"), 1500);
      return;
    }

    btnConfirmarPedido.disabled = true;
    btnConfirmarPedido.textContent = "Tramitando pedido...";

    try {
      const res = await Api.post("/pedidos", {
        notas: notasPedido.value.trim(),
        lineas: lineasPedido,
      });

      const pedido = res.pedido || res;
      lineasPedido = [];
      renderizarLineas();
      confirmacion.innerHTML = `
        <h3>¡Pedido #${pedido.id || pedido.codigo || "OK"} registrado!</h3>
        <p class="subtitulo-form">Hemos reservado tu stock y guardado las opciones de personalización.</p>
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
      const productos = await Api.get("/productos");
      const lista = Array.isArray(productos) ? productos : productos.productos || [];
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