import { index as productSdlIndex } from '@/routes/products/sdl';
import {
    createEditReturnBackRule,
    registerReturnBackRule,
    useReturnBack,
} from '@/composables/useReturnBack';

const sdlEditPathPattern = /^\/products\/(\d+)\/sdl\/(\d+)\/edit\/?$/;

export function sdlEditReturnScope(sdlRunId: number): string {
    return `sdl-edit-return.${sdlRunId}`;
}

registerReturnBackRule(
    createEditReturnBackRule({
        matchEdit: (pathname) => {
            const match = pathname.match(sdlEditPathPattern);

            return match ? sdlEditReturnScope(Number(match[2])) : null;
        },
        skipFrom: [/^\/products\/\d+\/sdl\/create\/?$/],
    }),
);

/**
 * Resolves Back target for SDL Edit: remembered origin, else product SDL index.
 */
export function useSdlEditBack(productId: number, sdlRunId: number) {
    return useReturnBack(
        sdlEditReturnScope(sdlRunId),
        () => productSdlIndex(productId).url,
    );
}
