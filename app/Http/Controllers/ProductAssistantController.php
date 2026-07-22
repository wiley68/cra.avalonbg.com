<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssistantDocumentAnalyseRequest;
use App\Http\Requests\StoreAssistantMessageRequest;
use App\Models\AiConversation;
use App\Models\Organization;
use App\Models\Product;
use App\Services\AiAssistantService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductAssistantController extends Controller
{
    public function __construct(
        private readonly AiAssistantService $assistant,
    ) {
    }

    public function show(Product $product): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        $user = request()->user();
        $conversation = $this->assistant->isEnabled()
            ? $this->assistant->latestForProductUser($product, $user)
            : null;

        return $this->renderChat($organization, $product, $conversation);
    }

    public function showConversation(Product $product, AiConversation $conversation): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);
        $this->assertConversationForProductUser($conversation, $product, request()->user()->id);

        return $this->renderChat($organization, $product, $conversation);
    }

    public function storeMessage(
        StoreAssistantMessageRequest $request,
        Product $product,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        $user = $request->user();
        $conversation = $this->assistant->getOrStartConversation($product, $user);

        $result = $this->assistant->sendMessage(
            $conversation,
            $user,
            (string) $request->validated('content'),
        );

        return redirect()->route('products.assistant.conversations.show', [
            'product' => $product,
            'conversation' => $result['conversation'],
        ]);
    }

    public function analyseDocument(
        StoreAssistantDocumentAnalyseRequest $request,
        Product $product,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        $result = $this->assistant->analyseDocument(
            $product,
            $request->user(),
            $request->file('file'),
            $request->validated('note'),
        );

        return redirect()->route('products.assistant.conversations.show', [
            'product' => $product,
            'conversation' => $result['conversation'],
        ]);
    }

    private function renderChat(
        Organization $organization,
        Product $product,
        ?AiConversation $conversation,
    ): InertiaResponse {
        return Inertia::render('products/assistant/Show', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'ai_enabled' => $this->assistant->isEnabled(),
            'provider' => $this->assistant->driver()->value,
            'conversation' => $this->assistant->conversationPayload($conversation),
        ]);
    }

    private function assertConversationForProductUser(
        AiConversation $conversation,
        Product $product,
        int $userId,
    ): void {
        if (
            $conversation->product_id !== $product->id
            || $conversation->user_id !== $userId
        ) {
            abort(404);
        }
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }

    private function assertProductInOrganization(Product $product, Organization $organization): void
    {
        if ($product->organization_id !== $organization->id) {
            abort(404);
        }
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function organizationPayload(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
        ];
    }
}
