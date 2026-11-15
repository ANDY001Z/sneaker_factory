"use strict";

document.addEventListener("DOMContentLoaded", () => {
    const tbody = document.getElementById("cuerpo-tabla-reservas");
    const filtroEstado = document.getElementById("filtro-estado");
    const formatoMoneda = new Intl.NumberFormat("es-ES", { style: "currency", currency: "EUR" });
    
    // Elementos del Modal Personalizado
    const modalConfirmacion = document.getElementById("modal-confirmacion");
    const btnCerrarModal = document.getElementById("btn-cerrar-modal");
    const btnConfirmarModal = document.getElementById("btn-confirmar-modal");
    
    let reservaPendienteId = null;
    let reservaPendienteAccion = null;

    // 1. Cargar reservas desde la API
    const cargarReservas = async (estado = "") => {
        try {
            let url = "/api/gestion/reservas";
            
            if (estado !== "") {
                url = `/api/gestion/reservas?estado=${estado}`;
            }
            
            const respuesta = await fetch(url);
            
            if (!respuesta.ok) {
                throw new Error("Error al obtener las reservas");
            } else {
                const reservas = await respuesta.json();
                renderizarTabla(reservas);
            }
        } catch (error) {
            console.error("Fallo de conexión:", error);
            tbody.innerHTML = "";
            const tr = document.createElement("tr");
            const td = document.createElement("td");
            td.colSpan = 7;
            td.style.textAlign = "center";
            td.style.color = "red";
            td.textContent = "Error al cargar los datos de la API.";
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
    };

    // 2. Pintar las filas en el DOM usando createElement
    const renderizarTabla = (reservas) => {
        tbody.innerHTML = ""; 

        if (reservas.length === 0) {
            const tr = document.createElement("tr");
            const td = document.createElement("td");
            td.colSpan = 7;
            td.style.textAlign = "center";
            td.textContent = "No hay reservas para mostrar.";
            tr.appendChild(td);
            tbody.appendChild(tr);
        } else {
            reservas.forEach(reserva => {
                const tr = document.createElement("tr");

                let botonesAccion = "";
                if (reserva.estado !== "cancelada") {
                    if (reserva.estado === "pendiente") {
                        botonesAccion += `<button class="btn-accion btn-confirmar" data-id="${reserva.id}" data-accion="confirmada">Confirmar</button>`;
                    }
                    botonesAccion += `<button class="btn-accion btn-cancelar" data-id="${reserva.id}" data-accion="cancelada">Cancelar</button>`;
                } else {
                    botonesAccion = `<span class="badge liberado">Stock liberado</span>`;
                }

                // Inyectamos atributos data-label para que la tabla sea responsiva en CSS
                tr.innerHTML = `
                    <td data-label="Código"><strong>${reserva.codigo}</strong></td>
                    <td data-label="Cliente">${reserva.nombre_cliente}<br><small>${reserva.email}</small></td>
                    <td data-label="Producto">${reserva.producto_nombre}</td>
                    <td data-label="Talla">${reserva.talla}</td>
                    <td data-label="Total">${formatoMoneda.format(reserva.total)}</td>
                    <td data-label="Estado"><span class="badge ${reserva.estado}">${reserva.estado}</span></td>
                    <td data-label="Acciones"><div class="acciones-wrapper">${botonesAccion}</div></td>
                `;
                tbody.appendChild(tr);
            });
        }
    };

    // 3. Cambiar el estado de la reserva (PATCH)
    const actualizarEstado = async (id, nuevoEstado) => {
        try {
            const respuesta = await fetch(`/api/gestion/reservas/${id}`, {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ 
                    estado: nuevoEstado 
                })
            });

            if (!respuesta.ok) {
                const data = await respuesta.json();
                alert(`Error: ${data.error}`);
            } else {
                cargarReservas(filtroEstado.value);
            }
        } catch (error) {
            console.error("Error al actualizar:", error);
            alert("Hubo un problema de conexión al intentar actualizar la reserva.");
        }
    };

    // 4. Delegación de eventos en la tabla
    tbody.addEventListener("click", (e) => {
        if (e.target.classList.contains("btn-accion")) {
            const idReserva = e.target.dataset.id;
            const accion = e.target.dataset.accion;
            
            if (accion === "cancelada") {
                // En lugar de confirm(), abrimos el modal personalizado
                reservaPendienteId = idReserva;
                reservaPendienteAccion = accion;
                modalConfirmacion.classList.remove("oculto");
            } else {
                actualizarEstado(idReserva, accion);
            }
        }
    });

    // 5. Control del Modal Personalizado
    btnCerrarModal.addEventListener("click", () => {
        modalConfirmacion.classList.add("oculto");
        reservaPendienteId = null;
        reservaPendienteAccion = null;
    });

    btnConfirmarModal.addEventListener("click", () => {
        if (reservaPendienteId !== null && reservaPendienteAccion !== null) {
            actualizarEstado(reservaPendienteId, reservaPendienteAccion);
            modalConfirmacion.classList.add("oculto");
            reservaPendienteId = null;
            reservaPendienteAccion = null;
        }
    });

    // 6. Escuchar cambios en el filtro select
    filtroEstado.addEventListener("change", (e) => {
        cargarReservas(e.target.value);
    });

    // Inicialización
    cargarReservas();
});