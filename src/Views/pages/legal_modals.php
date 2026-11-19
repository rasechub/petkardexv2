<?php
/**
 * PetKardex v2 - Modales de Políticas Legales
 *
 * Incluye: Términos y Condiciones, Política de Privacidad,
 * Política de Cookies y Política de Reembolso.
 */
?>
<!-- Modal: Términos y Condiciones -->
<div class="modal fade" id="modalTerminos" tabindex="-1" aria-labelledby="modalTerminosLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTerminosLabel">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Términos y Condiciones de Uso
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small"><strong>Última actualización:</strong> <?= date('d/m/Y') ?></p>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Al acceder y utilizar los servicios clínicos y la plataforma digital de <strong>PetKardex</strong>, usted acepta quedar sujeto a los presentes Términos y Condiciones de Uso.</p>

        <h5>1. Objeto y Ámbito de Aplicación</h5>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>

        <h5>2. Servicios Veterinarios y Plataforma Digital</h5>
        <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum, incluyendo consultas, hospitalización, farmacia veterinaria, estética e historial clínico mediante códigos QR.</p>

        <h5>3. Registro, Acceso y Responsabilidad de Cuentas</h5>
        <p>Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.</p>

        <h5>4. Uso del Código QR e Historial Clínico</h5>
        <p>Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. La vista pública de emergencia muestra únicamente alertas críticas y contacto autorizado.</p>

        <h5>5. Propiedad Intelectual y Derechos de Autor</h5>
        <p>PetKardex es una obra de software desarrollada por <strong>CESAR AQUINO ANGLAS - VI B NOCHE</strong> (IESTP Santiago Antúnez de Mayolo - IESTP SAM, 2026). Todos los derechos reservados. Queda terminantemente prohibida la copia, reproducción, ingeniería inversa o uso no autorizado conforme a la licencia del software.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary-pk" data-bs-dismiss="modal">Entendido</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Política de Privacidad -->
<div class="modal fade" id="modalPrivacidad" tabindex="-1" aria-labelledby="modalPrivacidadLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalPrivacidadLabel">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Política de Privacidad
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small"><strong>Última actualización:</strong> <?= date('d/m/Y') ?></p>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. En PetKardex protegemos la confidencialidad de los datos personales de los propietarios, médicos veterinarios y expedientes clínicos de cada paciente.</p>

        <h5>1. Responsable del Tratamiento de Datos</h5>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam. Sed nisi. Nulla quis sem at nibh elementum imperdiet.</p>

        <h5>2. Datos Recopilados y Finalidad</h5>
        <p>Duis sagittis ipsum. Praesent mauris. Fusce nec tellus sed augue semper porta. Mauris massa. Vestibulum lacinia arcu eget nulla. Recopilamos datos de identificación del propietario y datos médicos del paciente exclusivamente para la atención veterinaria y gestión de citas.</p>

        <h5>3. Seguridad, Cifrado y Control de Acceso</h5>
        <p>Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Curabitur sodales ligula in libero. Los registros clínicos están protegidos mediante autenticación por roles, sesiones cifradas y auditoría de acceso.</p>

        <h5>4. Derechos del Titular (ARCO)</h5>
        <p>Sed dignissim lacinia nunc. Curabitur tortor. Pellentesque nibh. Aenean quam. In scelerisque sem at dolor. Usted puede solicitar el acceso, rectificación, cancelación u oposición al tratamiento de sus datos personales.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary-pk" data-bs-dismiss="modal">Entendido</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Política de Cookies -->
<div class="modal fade" id="modalCookies" tabindex="-1" aria-labelledby="modalCookiesLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCookiesLabel">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
          Política de Cookies
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small"><strong>Última actualización:</strong> <?= date('d/m/Y') ?></p>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Esta política describe cómo PetKardex utiliza cookies técnicas y de preferencias para garantizar el funcionamiento seguro de la plataforma.</p>

        <h5>1. Definición de Cookies</h5>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas mattis. Sed convallis tristique sem. Proin ut ligula vel nunc egestas porttitor.</p>

        <h5>2. Cookies Estrictamente Necesarias</h5>
        <p>Morbi lectus risus, iaculis vel, suscipit quis, luctus non, massa. Fusce ac turpis quis ligula lacinia aliquet:</p>
        <ul>
          <li><code>PKDX_SESSION</code> — Identificador seguro de sesión (HttpOnly, SameSite=Strict).</li>
          <li><code>_csrf_token</code> — Token de verificación contra falsificación de peticiones entre sitios.</li>
        </ul>

        <h5>3. Cookies de Preferencias y Rendimiento</h5>
        <p>Mauris ipsum. Nulla metus metus, ullamcorper vel, tincidunt sed, euismod in, nibh. Quisque volutpat condimentum velit. Class aptent taciti sociosqu ad litora torquent per conubia nostra.</p>

        <h5>4. Administración en el Navegador</h5>
        <p>Nam nec ante. Sed lacinia, urna non tincidunt mattis, tortor neque adipiscing diam, a cursus ipsum ante quis turpis. Puede bloquear o eliminar cookies desde la configuración de su navegador.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-outline-pk" data-bs-dismiss="modal">Solo necesarias</button>
        <button type="button" class="btn-primary-pk" data-bs-dismiss="modal">Aceptar todas</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Política de Reembolso -->
<div class="modal fade" id="modalReembolso" tabindex="-1" aria-labelledby="modalReembolsoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalReembolsoLabel">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          Política de Reembolso
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small"><strong>Última actualización:</strong> <?= date('d/m/Y') ?></p>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Conoce nuestras condiciones de devolución, cancelación de citas y reembolsos aplicables a productos de farmacia, Pet Shop y servicios clínicos.</p>

        <h5>1. Cancelación y Reprogramación de Citas</h5>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer euismod lacus luctus magna. Quisque cursus, metus vitae pharetra auctor, sem massa mattis sem, at interdum magna augue eget diam.</p>

        <h5>2. Productos de Farmacia y Pet Shop</h5>
        <p>Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Morbi lacinia molestie dui. Praesent blandit dolor. Los medicamentos refrigerados o abiertos no admiten devolución por seguridad sanitaria.</p>

        <h5>3. Servicios de Hospitalización y Procedimientos</h5>
        <p>Sed non quam. In vel mi sit amet augue congue elementum. Morbi in ipsum sit amet pede facilisis laoreet. Donec lacus nunc, viverra nec, blandit vel, egestas et, augue.</p>

        <h5>4. Plazos y Proceso de Reembolso</h5>
        <p>Vestibulum tincidunt malesuada tellus. Ut ultrices ultrices enim. Curabitur sit amet mauris. Morbi in dui quis est pulvinar ullamcorper. Las solicitudes aprobadas se procesan al método de pago original dentro de 5 a 10 días hábiles.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-primary-pk" data-bs-dismiss="modal">Entendido</button>
      </div>
    </div>
  </div>
</div>

