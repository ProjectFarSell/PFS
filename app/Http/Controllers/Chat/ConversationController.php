<?php

namespace App\Http\Controllers\Chat;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);
        $conversations = $this->conversationList($user);

        return view('chat.index', [
            'conversations' => $conversations,
            'isSeller' => $user->role === UserRole::Seller,
            'unreadTotal' => (int) $conversations->sum('unread_count'),
        ]);
    }

    public function start(Request $request, Product $product): RedirectResponse
    {
        abort_unless($request->user()->role === UserRole::Buyer, 403);
        abort_unless($product->is_active && $product->shop?->is_active, 404);
        $conversation = Conversation::query()->firstOrCreate([
            'buyer_id' => $request->user()->id,
            'shop_id' => $product->shop_id,
            'product_id' => $product->id,
        ]);

        return to_route('chat.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorizeConversation($request, $conversation);
        $this->markRead($request, $conversation);
        $conversation->load(['buyer:id,name', 'shop:id,name,user_id', 'product:id,name']);
        $messages = $conversation->messages()->with('sender:id,name')->latest('id')->limit(100)->get()->reverse()->values();
        $conversations = $this->conversationList($request->user());

        return view('chat.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'conversations' => $conversations,
            'isSeller' => $request->user()->role === UserRole::Seller,
            'unreadTotal' => (int) $conversations->sum('unread_count'),
        ]);
    }

    public function widgetConversations(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        return response()->json($this->conversationList($user)->map(function (Conversation $conversation) use ($user) {
            $latest = $conversation->latestMessage;
            $counterpart = $user->role === UserRole::Seller ? $conversation->buyer->name : $conversation->shop->name;

            return [
                'id' => $conversation->id,
                'name' => $counterpart,
                'product' => $conversation->product?->name,
                'preview' => $latest?->body ?? ($conversation->product?->name ? '[Product] '.$conversation->product->name : 'Start a conversation'),
                'unread' => (int) $conversation->unread_count,
                'last_message_id' => $latest?->id ?? 0,
            ];
        }));
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);
        $this->markRead($request, $conversation);
        $after = max(0, $request->integer('after'));
        $query = $conversation->messages()->with('sender:id,name');
        $messages = $after === 0
            ? $query->latest('id')->limit(100)->get()->reverse()->values()
            : $query->where('id', '>', $after)->orderBy('id')->limit(100)->get();

        return response()->json($messages->map(fn (Message $message) => [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender' => $message->sender->name,
            'body' => $message->body,
            'created_at' => $message->created_at->format('M j, g:i a'),
        ]));
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeConversation($request, $conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message = $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => trim($data['body'])]);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender' => $request->user()->name,
                'body' => $message->body,
                'created_at' => $message->created_at->format('M j, g:i a'),
            ], 201);
        }

        return to_route('chat.show', $conversation);
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        $user = $request->user();
        $isBuyer = $conversation->buyer_id === $user->id && $user->role === UserRole::Buyer;
        $isShopOwner = $user->role === UserRole::Seller && $conversation->shop()->where('user_id', $user->id)->exists();
        abort_unless($isBuyer || $isShopOwner, 404);
    }

    private function markRead(Request $request, Conversation $conversation): void
    {
        $conversation->messages()->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)->update(['read_at' => now()]);
    }

    private function conversationList(User $user)
    {
        $query = Conversation::query()
            ->with(['buyer:id,name', 'shop:id,name,user_id', 'product:id,name', 'latestMessage.sender:id,name'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $user->id)])
            ->withMax('messages', 'id')
            ->latest('messages_max_id');

        if ($user->role === UserRole::Buyer) {
            return $query->where('buyer_id', $user->id)->get();
        }

        $shop = $user->shop;
        abort_unless($user->role === UserRole::Seller && $shop, 403);

        return $query->where('shop_id', $shop->id)->get();
    }
}
