import { onMounted, ref, watch } from 'vue';

type PaginatorResponse<T> = {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
};

type PaginationState = {
    page: number;
    rowsPerPage: number;
    rowsNumber: number;
    sortBy: string;
    descending: boolean;
};

type UseApiTableOptions = {
    endpoint: string;
    initial?: Partial<PaginationState> & { search?: string };
    onError?: (message: string) => void;
    autoload?: boolean;
    searchDebounceMs?: number;
    /** Extra query params merged into each request (e.g. version_id filter). */
    getExtraParams?: () => Record<string, string>;
};

function debounce<F extends (...args: never[]) => void>(fn: F, delay = 300) {
    let timeout: ReturnType<typeof setTimeout> | null = null;

    return (...args: Parameters<F>) => {
        if (timeout) {
            clearTimeout(timeout);
        }

        timeout = setTimeout(() => fn(...args), delay);
    };
}

export function useApiTable<T = unknown>(opts: UseApiTableOptions) {
    const {
        endpoint,
        initial,
        onError,
        autoload = true,
        searchDebounceMs = 400,
        getExtraParams,
    } = opts;

    const rows = ref<T[]>([]);
    const loading = ref(false);

    const pagination = ref<PaginationState>({
        page: initial?.page ?? 1,
        rowsPerPage: initial?.rowsPerPage ?? 10,
        rowsNumber: initial?.rowsNumber ?? 0,
        sortBy: initial?.sortBy ?? 'id',
        descending: initial?.descending ?? true,
    });

    const search = ref(initial?.search ?? '');

    let abortController: AbortController | null = null;

    async function fetchData() {
        loading.value = true;

        if (abortController) {
            abortController.abort();
        }

        abortController = new AbortController();

        const params = new URLSearchParams({
            page: String(pagination.value.page),
            per_page: String(pagination.value.rowsPerPage),
            sort_by: pagination.value.sortBy,
            sort_desc: pagination.value.descending ? '1' : '0',
            search: search.value,
            ...(getExtraParams?.() ?? {}),
        });

        try {
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: abortController.signal,
            });

            if (!response.ok) {
                const data = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;
                onError?.(data?.message ?? 'Failed to load data.');

                return;
            }

            const payload = (await response.json()) as PaginatorResponse<T>;

            rows.value = payload.data;
            pagination.value.rowsNumber = payload.total;
            pagination.value.page = payload.current_page;
            pagination.value.rowsPerPage = payload.per_page;
        } catch (error) {
            if ((error as Error).name !== 'AbortError') {
                onError?.('Failed to load data.');
            }
        } finally {
            loading.value = false;
        }
    }

    const debouncedFetch = debounce(() => fetchData(), searchDebounceMs);

    watch(search, () => {
        pagination.value.page = 1;
        debouncedFetch();
    });

    onMounted(() => {
        if (autoload) {
            fetchData();
        }
    });

    return {
        rows,
        pagination,
        loading,
        search,
        fetch: fetchData,
    };
}
