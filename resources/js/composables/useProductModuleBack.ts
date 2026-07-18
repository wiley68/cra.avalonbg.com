import { computed } from 'vue';
import { edit as editProduct, index as productsIndex } from '@/routes/products';

export type ProductModuleOrigin = 'edit' | 'index';

const storageKey = (productId: number): string =>
    `cra.product-module-origin.${productId}`;

export function setProductModuleOrigin(
    productId: number,
    origin: ProductModuleOrigin,
): void {
    try {
        sessionStorage.setItem(storageKey(productId), origin);
    } catch {
        // Ignore private-mode / storage failures.
    }
}

export function getProductModuleOrigin(productId: number): ProductModuleOrigin {
    try {
        return sessionStorage.getItem(storageKey(productId)) === 'edit'
            ? 'edit'
            : 'index';
    } catch {
        return 'index';
    }
}

/**
 * Resolves Back target for product submodule Index/Show pages
 * based on whether the user opened the module from products Index or Edit.
 */
export function useProductModuleBack(productId: number) {
    const backHref = computed(() =>
        getProductModuleOrigin(productId) === 'edit'
            ? editProduct(productId).url
            : productsIndex().url,
    );

    return { backHref };
}
