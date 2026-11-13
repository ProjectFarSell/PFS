<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Mobile\MobileEvents;
use App\Services\Mobile\PublicIds;
use Illuminate\Support\Facades\DB;

class ChatMessages
{
    public function send(Conversation $conversation, User $sender, string $body)
    {
        return DB::transaction(function () use ($conversation, $sender, $body) {
            $conversation = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            abort_unless(($sender->role->value === 'buyer' && $conversation->buyer_id === $sender->id) || ($sender->role->value === 'seller' && $conversation->shop?->user_id === $sender->id), 404);
            $message = $conversation->messages()->create(['sender_id' => $sender->id, 'body' => trim($body)]);
            $conversation->touch();
            app(MobileEvents::class)->record('chat:'.$message->id, 'chat.message_created', [$conversation->buyer_id], ['conversation_id' => PublicIds::id($conversation)]);

            return $message;
        });
    }
}
