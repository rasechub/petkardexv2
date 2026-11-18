<?php
/**
 * PetKardex v2 - Escáner Interno de Código QR para la Clínica
 *
 * Exclusivo para Veterinarios, Guardias, Emergencias, Recepcionistas y SuperAdmin.
 * Utiliza html5-qrcode vía CDN para leer códigos QR desde webcam o smartphone
 * y redirigir automáticamente al Kardex Médico Interno del paciente.
 *
 * Variables:
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

ob_start();
?>

<div class="pk-card p-4 mb-4 text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #1A1A2E 65%, #371F66 100%);">
  <div class="row align-items-center g-3">
    <div class="col-lg-8">
      <span class="badge rounded-pill px-3 py-2 mb-2" style="background: rgba(196, 140, 255, 0.2); color: #C48CFF;">
        <i class="bi bi-qr-code-scan me-1"></i>Uso Clínico Interno
      </span>
      <h2 class="h4 fw-bold mb-1">Escáner QR de Pacientes</h2>
      <p class="mb-0 small text-white-50">
        Apunta la cámara de recepción o tu dispositivo móvil hacia la credencial QR de la mascota
        para abrir directamente su Kardex Médico Interno.
      </p>
    </div>
    <div class="col-lg-4 text-lg-end">
      <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">
        <i class="bi bi-shield-check me-1"></i>Redirección a Kardex Médico
      </span>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Lector de Cámara -->
  <div class="col-lg-7">
    <div class="pk-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h3 class="h6 fw-bold mb-0">
          <i class="bi bi-camera-video-fill me-2" style="color: #7B52AB;"></i>Lector por Cámara
        </h3>
        <div class="d-flex gap-2">
          <button type="button" id="btnStartScan" class="btn btn-sm btn-pk-primary px-3">
            <i class="bi bi-play-fill me-1"></i>Iniciar Cámara
          </button>
          <button type="button" id="btnStopScan" class="btn btn-sm btn-outline-dark px-3 d-none">
            <i class="bi bi-stop-fill me-1"></i>Detener
          </button>
        </div>
      </div>

      <div class="mb-3">
        <label for="cameraSelect" class="form-label small fw-semibold text-muted">Cámara disponible</label>
        <select id="cameraSelect" class="form-select form-select-sm rounded-3">
          <option value="">Detectando cámaras...</option>
        </select>
      </div>

      <!-- Contenedor html5-qrcode -->
      <div
        id="qr-reader"
        class="rounded-4 overflow-hidden border bg-dark d-flex align-items-center justify-content-center"
        style="min-height: 320px; border-color: rgba(196, 140, 255, 0.45) !important;"
      >
        <div class="text-center text-white-50 p-4">
          <i class="bi bi-qr-code-scan d-block mb-2" style="font-size: 2.75rem; color: #C48CFF;"></i>
          <div class="small">Presiona <strong>Iniciar Cámara</strong> para comenzar el escaneo.</div>
        </div>
      </div>

      <div id="scanStatus" class="alert alert-secondary mt-3 mb-0 small d-none" role="alert"></div>
    </div>
  </div>

  <!-- Búsqueda Manual / Respaldo -->
  <div class="col-lg-5">
    <div class="pk-card p-4 mb-4">
      <h3 class="h6 fw-bold mb-2">
        <i class="bi bi-keyboard me-2" style="color: #7B52AB;"></i>Ingreso Manual o Lector USB
      </h3>
      <p class="small text-muted mb-3">
        Si utilizas un escáner de mano USB o deseas buscar manualmente, pega el UUID del QR,
        la URL completa o el número de microchip del paciente.
      </p>

      <form id="manualResolveForm" novalidate>
        <div class="mb-3">
          <label for="manualQrInput" class="form-label small fw-semibold">Código QR / UUID / Microchip</label>
          <input
            type="text"
            id="manualQrInput"
            class="form-control rounded-3"
            placeholder="Ej: 550e8400-e29b-41d4-a716-446655440000"
            required
          />
        </div>
        <div class="d-grid">
          <button type="submit" id="btnManualResolve" class="btn btn-dark rounded-3 py-2 fw-semibold">
            <i class="bi bi-box-arrow-in-right me-2"></i>Abrir Kardex Médico
          </button>
        </div>
      </form>
    </div>

    <div class="pk-card p-4" style="background: #F8F5FF; border-color: rgba(196, 140, 255, 0.35);">
      <h4 class="h6 fw-bold mb-2" style="color: #5A3A8A;">
        <i class="bi bi-info-circle-fill me-2"></i>Flujo de Seguridad Clínica
      </h4>
      <ul class="small text-muted ps-3 mb-0 d-grid gap-1">
        <li>El escaneo interno verifica tu sesión y rol antes de resolver el expediente.</li>
        <li>Redirige automáticamente a <code>/app/kardex/{id}</code> para edición clínica.</li>
        <li>Si la placa física fue dañada, también puedes buscar al paciente por microchip.</li>
      </ul>
    </div>
  </div>
</div>

<!-- html5-qrcode vía CDN jsDelivr -->
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js" crossorigin="anonymous"></script>

<script>
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = <?= json_encode((string) ($csrfToken ?? ''), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const cameraSelect = document.getElementById('cameraSelect');
    const btnStart = document.getElementById('btnStartScan');
    const btnStop = document.getElementById('btnStopScan');
    const statusBox = document.getElementById('scanStatus');
    const manualForm = document.getElementById('manualResolveForm');
    const manualInput = document.getElementById('manualQrInput');

    let html5Qr = null;
    let isScanning = false;
    let isResolving = false;

    function showStatus(message, type = 'secondary') {
      statusBox.className = `alert alert-${type} mt-3 mb-0 small`;
      statusBox.textContent = message;
      statusBox.classList.remove('d-none');
    }

    async function resolveQrPayload(payload) {
      if (isResolving || !payload) return;
      isResolving = true;
      showStatus('Validando código QR en el sistema clínico...', 'info');

      try {
        const res = await fetch('/app/qr/resolver', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({ qr_data: payload })
        });

        const data = await res.json();

        if (!res.ok || !data.success || !data.redirect) {
          showStatus(data.error || 'No se pudo identificar al paciente.', 'danger');
          isResolving = false;
          return;
        }

        const petLabel = data.paciente ? `${data.paciente.nombre} (${data.paciente.especie})` : 'Paciente';
        showStatus(`Identificado: ${petLabel}. Abriendo Kardex Médico...`, 'success');

        if (isScanning && html5Qr) {
          try { await html5Qr.stop(); } catch (_) {}
          isScanning = false;
        }

        window.location.href = data.redirect;
      } catch (err) {
        showStatus('Error de comunicación al resolver el código QR.', 'danger');
        isResolving = false;
      }
    }

    async function loadCameras() {
      if (typeof Html5Qrcode === 'undefined') {
        cameraSelect.innerHTML = '<option value="">Librería de escaneo no disponible</option>';
        showStatus('No se pudo cargar html5-qrcode. Usa el ingreso manual.', 'warning');
        return;
      }

      try {
        const devices = await Html5Qrcode.getCameras();
        if (!devices || devices.length === 0) {
          cameraSelect.innerHTML = '<option value="">No se detectaron cámaras</option>';
          return;
        }

        cameraSelect.innerHTML = '';
        devices.forEach((cam, idx) => {
          const opt = document.createElement('option');
          opt.value = cam.id;
          opt.textContent = cam.label || `Cámara ${idx + 1}`;
          if (/back|rear|trasera|environment/i.test(opt.textContent)) {
            opt.selected = true;
          }
          cameraSelect.appendChild(opt);
        });
      } catch (err) {
        cameraSelect.innerHTML = '<option value="">Permiso de cámara requerido</option>';
      }
    }

    btnStart.addEventListener('click', async () => {
      if (typeof Html5Qrcode === 'undefined') return;
      if (isScanning) return;

      const cameraId = cameraSelect.value || { facingMode: 'environment' };
      html5Qr = new Html5Qrcode('qr-reader');

      try {
        await html5Qr.start(
          cameraId,
          { fps: 10, qrbox: { width: 240, height: 240 } },
          (decodedText) => {
            resolveQrPayload(decodedText);
          },
          () => {}
        );
        isScanning = true;
        btnStart.classList.add('d-none');
        btnStop.classList.remove('d-none');
        showStatus('Cámara activa. Apunta hacia el código QR de la mascota.', 'primary');
      } catch (err) {
        showStatus('No fue posible iniciar la cámara. Verifica los permisos del navegador o usa HTTPS/localhost.', 'danger');
      }
    });

    btnStop.addEventListener('click', async () => {
      if (html5Qr && isScanning) {
        try {
          await html5Qr.stop();
        } catch (_) {}
      }
      isScanning = false;
      btnStop.classList.add('d-none');
      btnStart.classList.remove('d-none');
      showStatus('Escaneo detenido.', 'secondary');
    });

    manualForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const val = manualInput.value.trim();
      if (!val) {
        showStatus('Ingresa un UUID, URL QR o microchip válido.', 'warning');
        return;
      }
      resolveQrPayload(val);
    });

    loadCameras();
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';

