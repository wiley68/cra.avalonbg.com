<script setup lang="ts" generic="TData">
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    ChevronsLeft,
    ChevronsRight,
    Loader2,
    Settings,
} from '@lucide/vue';
import type {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
} from '@tanstack/vue-table';
import {
    FlexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getSortedRowModel,
    useVueTable,
} from '@tanstack/vue-table';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/composables/useTranslations';
import { cn, valueUpdater } from '@/lib/utils';

interface DataTableProps {
    columns: ColumnDef<TData>[];
    data: TData[];
    loading?: boolean;
    searchPlaceholder?: string;
    emptyMessage?: string;
    loadingMessage?: string;
    showPagination?: boolean;
    showColumnToggle?: boolean;
    pageSize?: number;
    search?: string;
    columnTitleMap?: Record<string, string>;
    currentPage?: number;
    totalPages?: number;
    totalItems?: number;
    onSearchChange?: (value: string) => void;
    onPaginationChange?: (page: number, pageSize: number) => void;
    onSortingChange?: (sorting: SortingState) => void;
    serverSide?: boolean;
    expandable?: boolean;
    expandedRows?: Record<string, boolean>;
    onRowExpand?: (rowId: string, expanded: boolean) => void;
    expandedRowContent?: (row: TData) => unknown;
    fillHeight?: boolean;
}

const props = withDefaults(defineProps<DataTableProps>(), {
    loading: false,
    showPagination: true,
    showColumnToggle: true,
    pageSize: 10,
    search: '',
    currentPage: 1,
    totalPages: 1,
    totalItems: 0,
    expandable: false,
    expandedRows: () => ({}),
    fillHeight: false,
    serverSide: false,
});

const { t } = useTranslations();

const resolvedSearchPlaceholder = computed(
    () => props.searchPlaceholder ?? t('common.table.search_placeholder'),
);
const resolvedEmptyMessage = computed(
    () => props.emptyMessage ?? t('common.table.empty'),
);
const resolvedLoadingMessage = computed(
    () => props.loadingMessage ?? t('common.table.loading'),
);
const pageLabel = computed(() =>
    t('common.table.page_of', {
        current: String(props.currentPage),
        total: String(props.totalPages),
    }),
);

const sorting = ref<SortingState>([]);
const columnFilters = ref<ColumnFiltersState>([]);
const columnVisibility = ref<VisibilityState>({});

const table = useVueTable({
    data: computed(() => props.data),
    columns: props.columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: props.serverSide ? undefined : getSortedRowModel(),
    getFilteredRowModel: props.serverSide ? undefined : getFilteredRowModel(),
    manualSorting: props.serverSide,
    manualPagination: props.serverSide,
    pageCount: props.serverSide ? props.totalPages : undefined,
    onSortingChange: (updaterOrValue) => valueUpdater(updaterOrValue, sorting),
    onColumnFiltersChange: (updaterOrValue) =>
        valueUpdater(updaterOrValue, columnFilters),
    onColumnVisibilityChange: (updaterOrValue) =>
        valueUpdater(updaterOrValue, columnVisibility),
    state: {
        get sorting() {
            return sorting.value;
        },
        get columnFilters() {
            return columnFilters.value;
        },
        get columnVisibility() {
            return columnVisibility.value;
        },
    },
});

const search = computed({
    get: () => props.search,
    set: (value: string) => props.onSearchChange?.(value),
});

watch(
    sorting,
    (newSorting, oldSorting) => {
        if (
            oldSorting &&
            JSON.stringify(newSorting) !== JSON.stringify(oldSorting)
        ) {
            props.onSortingChange?.(newSorting);
        }
    },
    { deep: true },
);

const getColumnTitle = (columnId: string) => {
    return props.columnTitleMap?.[columnId] || columnId;
};
</script>

<template>
    <div
        :class="
            cn('w-full', props.fillHeight && 'flex h-full min-h-0 flex-col')
        "
    >
        <div :class="cn('space-y-3 py-3', props.fillHeight && 'shrink-0')">
            <div class="flex items-center gap-4">
                <Input
                    v-model="search"
                    :placeholder="resolvedSearchPlaceholder"
                    class="flex-1"
                />
                <div v-if="showColumnToggle" class="flex items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm">
                                <Settings class="mr-2 h-4 w-4" />
                                {{ t('common.table.columns') }}
                                <ChevronDown class="ml-2 h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-[150px]">
                            <DropdownMenuLabel>{{
                                t('common.table.show_columns')
                            }}</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuCheckboxItem
                                v-for="column in table
                                    .getAllColumns()
                                    .filter((column) => column.getCanHide())"
                                :key="column.id"
                                class="capitalize"
                                :model-value="column.getIsVisible()"
                                @update:model-value="
                                    (value) => column.toggleVisibility(!!value)
                                "
                            >
                                {{ getColumnTitle(column.id) }}
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div
            :class="
                cn(
                    'rounded-md border',
                    props.fillHeight &&
                        'flex min-h-0 flex-1 flex-col overflow-hidden',
                )
            "
        >
            <div
                :class="cn(props.fillHeight && 'min-h-0 flex-1 overflow-auto')"
            >
                <Table>
                    <TableHeader>
                        <TableRow
                            v-for="headerGroup in table.getHeaderGroups()"
                            :key="headerGroup.id"
                        >
                            <TableHead v-if="expandable" class="w-12" />
                            <TableHead
                                v-for="header in headerGroup.headers"
                                :key="header.id"
                            >
                                <FlexRender
                                    v-if="!header.isPlaceholder"
                                    :render="header.column.columnDef.header"
                                    :props="header.getContext()"
                                />
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="loading">
                            <TableRow>
                                <TableCell
                                    :colspan="
                                        columns.length + (expandable ? 1 : 0)
                                    "
                                    class="h-24 text-center"
                                >
                                    <div
                                        class="flex items-center justify-center gap-2"
                                    >
                                        <Loader2 class="h-4 w-4 animate-spin" />
                                        {{ resolvedLoadingMessage }}
                                    </div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else-if="table.getRowModel().rows?.length">
                            <template
                                v-for="row in table.getRowModel().rows"
                                :key="row.id"
                            >
                                <TableRow>
                                    <TableCell v-if="expandable" class="w-12">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="
                                                onRowExpand?.(
                                                    row.id,
                                                    !expandedRows[row.id],
                                                )
                                            "
                                        >
                                            <ChevronUp
                                                v-if="expandedRows[row.id]"
                                                class="h-4 w-4"
                                            />
                                            <ChevronDown
                                                v-else
                                                class="h-4 w-4"
                                            />
                                        </Button>
                                    </TableCell>
                                    <TableCell
                                        v-for="cell in row.getVisibleCells()"
                                        :key="cell.id"
                                    >
                                        <FlexRender
                                            :render="cell.column.columnDef.cell"
                                            :props="cell.getContext()"
                                        />
                                    </TableCell>
                                </TableRow>

                                <TableRow
                                    v-if="expandable && expandedRows[row.id]"
                                    class="bg-muted/50"
                                >
                                    <TableCell
                                        :colspan="
                                            columns.length +
                                            (expandable ? 1 : 0)
                                        "
                                        class="p-0"
                                    >
                                        <div class="p-4">
                                            <FlexRender
                                                v-if="expandedRowContent"
                                                :render="expandedRowContent"
                                                :props="{
                                                    row: row.original,
                                                }"
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </template>
                        <TableRow v-else>
                            <TableCell
                                :colspan="columns.length + (expandable ? 1 : 0)"
                                class="h-24 text-center"
                            >
                                {{ resolvedEmptyMessage }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <div
            v-if="showPagination"
            :class="
                cn(
                    'flex items-center justify-between py-4',
                    props.fillHeight && 'shrink-0',
                )
            "
        >
            <div class="flex items-center space-x-2">
                <p class="text-sm font-medium">
                    {{ t('common.table.rows_per_page') }}
                </p>
                <Select
                    :model-value="`${pageSize}`"
                    @update:model-value="
                        (value) => onPaginationChange?.(1, Number(value))
                    "
                >
                    <SelectTrigger class="h-8 w-[70px]">
                        <SelectValue :placeholder="`${pageSize}`" />
                    </SelectTrigger>
                    <SelectContent side="top">
                        <SelectItem
                            v-for="size in [10, 20, 30, 40, 50]"
                            :key="size"
                            :value="`${size}`"
                        >
                            {{ size }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex items-center space-x-6 lg:space-x-8">
                <div
                    class="flex w-[140px] items-center justify-center text-sm font-medium"
                >
                    {{ pageLabel }}
                </div>
                <div class="flex items-center space-x-2">
                    <Button
                        variant="outline"
                        class="hidden h-8 w-8 p-0 lg:flex"
                        :disabled="currentPage <= 1"
                        @click="onPaginationChange?.(1, pageSize)"
                    >
                        <span class="sr-only">{{
                            t('common.table.first_page')
                        }}</span>
                        <ChevronsLeft class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        class="h-8 w-8 p-0"
                        :disabled="currentPage <= 1"
                        @click="onPaginationChange?.(currentPage - 1, pageSize)"
                    >
                        <span class="sr-only">{{
                            t('common.table.previous_page')
                        }}</span>
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        class="h-8 w-8 p-0"
                        :disabled="currentPage >= totalPages"
                        @click="onPaginationChange?.(currentPage + 1, pageSize)"
                    >
                        <span class="sr-only">{{
                            t('common.table.next_page')
                        }}</span>
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        class="hidden h-8 w-8 p-0 lg:flex"
                        :disabled="currentPage >= totalPages"
                        @click="onPaginationChange?.(totalPages, pageSize)"
                    >
                        <span class="sr-only">{{
                            t('common.table.last_page')
                        }}</span>
                        <ChevronsRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
