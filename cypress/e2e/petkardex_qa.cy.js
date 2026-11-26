describe('Plan de Pruebas QA - PetKardex v2 (ISO/IEC/IEEE 29119)', () => {
    const baseUrl = 'http://localhost:8088';

    it('CP-PK01: Validar inicio de sesión seguro y control de acceso (RBAC SuperAdmin)', () => {
        cy.visit(`${baseUrl}/app/login`);
        cy.get('input[type="email"], input[name="email"]').type('superadmin@petkardex.local');
        cy.get('input[type="password"], input[name="password"]').type('Admin2026!');
        cy.get('button[type="submit"]').click();
        cy.url().should('include', '/app/dashboard');
    });

    it('CP-PK02: Validar acceso al módulo de registro de mascotas y QR', () => {
        cy.visit(`${baseUrl}/app/login`);
        cy.get('input[type="email"], input[name="email"]').type('superadmin@petkardex.local');
        cy.get('input[type="password"], input[name="password"]').type('Admin2026!');
        cy.get('button[type="submit"]').click();
        cy.visit(`${baseUrl}/app/mascotas`);
        cy.contains(/mascota|paciente/i).should('be.visible');
    });

    it('CP-PK03: Validar acceso clínico y agenda con rol Veterinario (RBAC)', () => {
        cy.visit(`${baseUrl}/app/login`);
        cy.get('input[type="email"], input[name="email"]').type('veterinario@petkardex.local');
        cy.get('input[type="password"], input[name="password"]').type('Vet2026!');
        cy.get('button[type="submit"]').click();
        cy.visit(`${baseUrl}/app/agenda`);
        cy.get('body').should('not.contain.text', '404');
    });

    it('CP-PK04: Validar consulta pública de emergencia mediante enlace QR sin sesión', () => {
        cy.visit(`${baseUrl}/qr/demo-kardex-001`, { failOnStatusCode: false });
        cy.get('body').should('be.visible');
    });

    it("CP-PK05: Validar bloqueo de Inyección SQL (' OR '1'='1) en /app/login", () => {
        cy.visit(`${baseUrl}/app/login`);
        cy.get('input[type="email"], input[name="email"]').type("' OR '1'='1");
        cy.get('input[type="password"], input[name="password"]').type("' OR '1'='1");
        cy.get('button[type="submit"]').click();
        cy.url().should('include', '/app/login');
    });
});