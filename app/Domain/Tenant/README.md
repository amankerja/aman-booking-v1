# Domain: Tenant

## Tanggung Jawab
- Manajemen tenant (penyewa SaaS), isolasi data multi-tenant.
- Resolver tenant berbasis domain/subdomain/header/route slug (`ResolveTenant`).
- Implementasi `BelongsToTenant` trait dan global `TenantScope`.
