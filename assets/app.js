// Lavandería — comportamiento del lado del navegador (sin librerías).
(function () {
  'use strict';

  // Confirmación para acciones delicadas: <button data-confirmar="¿Seguro?">
  document.addEventListener('submit', function (ev) {
    var boton = ev.submitter;
    var mensaje = (boton && boton.dataset.confirmar) || ev.target.dataset.confirmar;
    if (mensaje && !window.confirm(mensaje)) ev.preventDefault();
  });

  var form = document.getElementById('form-registro');
  if (!form) return;

  var LADO_MAX = 1280;
  var totalKg = document.getElementById('total-kg');
  var botonGuardar = document.getElementById('boton-guardar');

  function numero(texto) {
    var n = parseFloat(String(texto).replace(',', '.'));
    return isFinite(n) && n > 0 ? n : 0;
  }

  // Suma en vivo del peso y resaltado de las áreas con datos.
  function recalcular() {
    var total = 0;
    form.querySelectorAll('.area').forEach(function (area) {
      var peso = numero(area.querySelector('.peso').value);
      total += peso;
      area.classList.toggle('con-datos', peso > 0);
    });
    totalKg.textContent = total.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg';
  }
  form.addEventListener('input', recalcular);
  recalcular();

  // Reduce la foto en el celular antes de subirla (más rápido y liviano en el wifi).
  function comprimir(archivo) {
    return createImageBitmap(archivo).then(function (img) {
      var escala = Math.min(1, LADO_MAX / Math.max(img.width, img.height));
      var lienzo = document.createElement('canvas');
      lienzo.width = Math.round(img.width * escala);
      lienzo.height = Math.round(img.height * escala);
      lienzo.getContext('2d').drawImage(img, 0, 0, lienzo.width, lienzo.height);
      return new Promise(function (ok) {
        lienzo.toBlob(function (blob) { ok(blob); }, 'image/jpeg', 0.75);
      });
    });
  }

  form.querySelectorAll('.area input[type=file]').forEach(function (entrada) {
    entrada.addEventListener('change', function () {
      var area = entrada.closest('.area');
      var mini = area.querySelector('.miniatura');
      var texto = area.querySelector('.texto-foto');
      var archivo = entrada.files && entrada.files[0];
      if (!archivo) { mini.classList.add('oculto'); texto.textContent = 'Foto'; return; }
      texto.textContent = '…';
      comprimir(archivo).then(function (blob) {
        // Reemplaza el archivo original por el comprimido (si el navegador lo permite).
        if (blob && window.DataTransfer) {
          var dt = new DataTransfer();
          dt.items.add(new File([blob], 'foto.jpg', { type: 'image/jpeg' }));
          entrada.files = dt.files;
        }
        mini.src = URL.createObjectURL(blob || archivo);
      }).catch(function () {
        mini.src = URL.createObjectURL(archivo); // sin compresión: el servidor igual la reduce
      }).then(function () {
        mini.classList.remove('oculto');
        texto.textContent = 'Cambiar';
      });
    });
  });

  // Marca las áreas que ya tienen registro para la fecha, recorrido y tipo elegidos.
  function marcarRegistradas() {
    var fecha = form.fecha.value;
    var rec = form.querySelector('input[name=recorrido]:checked');
    var tipo = form.querySelector('input[name=tipo]:checked');
    var areas = form.querySelectorAll('.area');
    if (!fecha || !rec || !tipo) {
      areas.forEach(function (a) { estadoArea(a, false); });
      return;
    }
    var url = 'registrar.php?existentes=1&fecha=' + encodeURIComponent(fecha) +
      '&recorrido=' + rec.value + '&tipo=' + tipo.value;
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (ids) {
        areas.forEach(function (a) { estadoArea(a, ids.indexOf(Number(a.dataset.area)) !== -1); });
      })
      .catch(function () { /* sin conexión: el servidor valida al guardar */ });
  }

  function estadoArea(area, registrada) {
    area.classList.toggle('registrada', registrada);
    area.querySelector('.etiqueta').classList.toggle('oculto', !registrada);
    area.querySelectorAll('input, textarea').forEach(function (c) { c.disabled = registrada; });
  }

  form.addEventListener('change', function (ev) {
    if (ev.target.name === 'fecha' || ev.target.name === 'recorrido' || ev.target.name === 'tipo') marcarRegistradas();
  });
  marcarRegistradas();

  // Evita doble envío y avisa si falta el recorrido.
  form.addEventListener('submit', function (ev) {
    if (!form.querySelector('input[name=recorrido]:checked')) {
      ev.preventDefault();
      alert('Elige el número de recorrido.');
      return;
    }
    botonGuardar.disabled = true;
    botonGuardar.textContent = 'Guardando…';
  });
})();

// Formulario de dietas: totales en vivo, botón «=» y aviso de área ya registrada.
(function () {
  'use strict';

  var form = document.getElementById('form-dieta');
  if (!form) return;

  var totalDietas = document.getElementById('total-dietas');
  var botonGuardar = document.getElementById('boton-guardar');
  var avisoRegistrada = document.getElementById('aviso-registrada');

  function entero(texto) {
    var n = parseInt(texto, 10);
    return isFinite(n) && n > 0 ? n : 0;
  }

  function recalcular() {
    var pedidas = 0, entregadas = 0;
    form.querySelectorAll('.dieta-fila').forEach(function (fila) {
      var p = entero(fila.querySelector('.pedidas').value);
      var e = entero(fila.querySelector('.entregadas').value);
      var dif = e - p;
      pedidas += p;
      entregadas += e;
      var celda = fila.querySelector('.diferencia');
      celda.textContent = dif > 0 ? '+' + dif : String(dif);
      celda.classList.toggle('negativo', dif < 0);
      celda.classList.toggle('positivo', dif > 0);
      fila.classList.toggle('con-datos', p > 0 || e > 0);
    });
    totalDietas.textContent = pedidas + ' / ' + entregadas;
    totalDietas.classList.toggle('negativo', pedidas !== entregadas);
  }
  form.addEventListener('input', recalcular);
  recalcular();

  form.querySelectorAll('.copiar').forEach(function (boton) {
    boton.addEventListener('click', function () {
      var fila = boton.closest('.dieta-fila');
      fila.querySelector('.entregadas').value = fila.querySelector('.pedidas').value;
      recalcular();
    });
  });

  // Solo en el registro nuevo: avisa si el área ya tiene entrega en ese tiempo de comida.
  function marcarRegistrada() {
    if (!avisoRegistrada) return;
    var fecha = form.fecha.value;
    var tiempo = form.querySelector('input[name=tiempo]:checked');
    var area = form.area.value;
    if (!fecha || !tiempo || !area) { avisoRegistrada.classList.add('oculto'); return; }
    fetch('registrar-dieta.php?existentes=1&fecha=' + encodeURIComponent(fecha) + '&tiempo=' + encodeURIComponent(tiempo.value),
      { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (ids) { avisoRegistrada.classList.toggle('oculto', ids.indexOf(Number(area)) === -1); })
      .catch(function () { /* sin conexión: el servidor valida al guardar */ });
  }
  form.addEventListener('change', function (ev) {
    if (ev.target.name === 'fecha' || ev.target.name === 'tiempo' || ev.target.name === 'area') marcarRegistrada();
  });
  marcarRegistrada();

  // Evita doble envío (después de la confirmación de «Eliminar», si la hay).
  form.addEventListener('submit', function (ev) {
    if (avisoRegistrada && !form.querySelector('input[name=tiempo]:checked')) {
      ev.preventDefault();
      alert('Elige el tiempo de comida.');
      return;
    }
    setTimeout(function () {
      if (ev.defaultPrevented) return;
      botonGuardar.disabled = true;
      botonGuardar.textContent = 'Guardando…';
    }, 0);
  });
})();
