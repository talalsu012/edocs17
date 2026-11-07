<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

/** جرس التنبيهات: عدد غير المقروء وآخر التنبيهات، يتحدث كل دقيقة. */
class NotificationBell extends Component
{
    public bool $open = false;

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    #[Computed]
    public function latest()
    {
        return auth()->user()->notifications()->latest()->limit(12)->get();
    }

    public function go(string $id)
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return $this->redirectRoute('letters.show', $n->data['letter_id'], navigate: true);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        unset($this->unreadCount, $this->latest);
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
