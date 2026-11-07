<?php

namespace App\Livewire;

use App\Models\BroadcastRead;
use App\Services\Mailbox as MailboxService;
use App\Support\ActiveUnit;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Mailbox extends Component
{
    use WithPagination;

    public string $box = 'inbox';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(string $box): void
    {
        abort_unless(array_key_exists($box, MailboxService::BOXES), 404);
        $this->box = $box;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(MailboxService $mailbox)
    {
        $unit = ActiveUnit::get();

        $letters = $mailbox->query(auth()->user(), $this->box, $unit, $this->search)->paginate(20);

        return view('livewire.mailbox', [
            'letters' => $letters,
            'readBroadcasts' => $this->box === 'broadcasts'
                ? BroadcastRead::where('user_id', auth()->id())
                    ->whereIn('letter_id', $letters->pluck('id'))->pluck('letter_id')->all()
                : [],
            'unit' => $unit,
            'title' => MailboxService::BOXES[$this->box],
        ])->title(MailboxService::BOXES[$this->box]);
    }
}
