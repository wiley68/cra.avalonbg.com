# Next Rewrite — API & DataTable

**Версия:** 1.0  
**Laravel refs:** `resources/js/composables/useApiTable.ts`, `resources/js/components/DataTable.vue`, `app/Http/Controllers/Api/**`, `.cursor/rules/server-side-index-tables.mdc`

---

## 1. Принцип

Index страниците **не** зареждат пълния списък през RSC props.

1. Page = shell (title, primary CTA, `<DataTable />`).
2. Data = `GET /api/...` JSON paginator.
3. Client = `useApiTable` еквивалент (React hook) + shared DataTable.

---

## 2. Query params (задължителни)

| Param       | Type       | Notes                  |
| ----------- | ---------- | ---------------------- |
| `page`      | int ≥ 1    | default 1              |
| `per_page`  | int 1–100  | default 10             |
| `sort_by`   | string     | whitelist per resource |
| `sort_desc` | `0` \| `1` | default `0`            |
| `search`    | string     | optional, max 255      |

Допълнителни филтри: `getExtraParams` pattern (напр. `product_id`, `version_id`).

---

## 3. Response shape (Laravel paginator parity)

```json
{
    "data": [{ "...": "row" }],
    "current_page": 1,
    "per_page": 10,
    "total": 42
}
```

Можеш да добавиш `last_page`, `from`, `to` — UI-то днес ползва основно горните четири.

Helper: `src/lib/datatable.ts`

```ts
// parseDatatableQuery(searchParams, { sortable: ['id','name',...], defaultSort: 'name' })
// jsonPaginator({ data, page, perPage, total })
```

---

## 4. Route map (примери)

| Laravel                                    | Next Route Handler                                     |
| ------------------------------------------ | ------------------------------------------------------ |
| `GET .../internal-api/admin/organizations` | `GET /api/admin/organizations`                         |
| User API                                   | `GET /api/users`                                       |
| Product API                                | `GET /api/products`                                    |
| Nested product resources                   | `GET /api/products/[productId]/vulnerabilities` и т.н. |

Auth: session cookie на същия origin; `assertPermission` във всеки handler.

Mutations: Server Actions **или** `POST/PUT/PATCH/DELETE` Route Handlers — избери едно на домейн и бъди консистентен (препоръка: Route Handlers за API tables + Server Actions за прости forms).

---

## 5. Frontend hook (React)

Портни поведението на `useApiTable`:

- state: `page`, `rowsPerPage`, `rowsNumber`, `sortBy`, `descending`, `search`
- debounce search ~300ms
- `fetch(endpoint + query)`
- `onError` toast

DataTable features: server-side sort, column toggle, pagination — като Laravel `DataTable.vue`.

---

## 6. Reference implementation (в Laravel клонинг)

Пътища относителни към `LARAVEL_REFERENCE_ROOT` (локален Laravel clone):

1. API: `app/Http/Controllers/Api/Admin/OrganizationApiController.php`
2. Hook: `resources/js/composables/useApiTable.ts`
3. UI: `resources/js/components/DataTable.vue`
4. Columns: `resources/js/pages/admin/organizations/columns.ts`
5. Index shell: `resources/js/pages/admin/organizations/Index.vue`

Etap 0 sample: Organizations stub table за platform admin.

---

## 7. История

| Версия | Дата       | Промяна            |
| ------ | ---------- | ------------------ |
| 1.0    | 2026-07-29 | DataTable contract |
