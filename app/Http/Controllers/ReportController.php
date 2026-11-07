<?php

namespace App\Http\Controllers;

use App\Enums\LetterStatus;
use App\Models\Letter;
use App\Models\LetterRecipient;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /** دفتر الوارد والصادر (مو15) — لمستخدم الجهة نفسها. */
    public function register(Request $request)
    {
        $data = $request->validate([
            'unit' => 'required|integer|exists:units,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'dir' => 'required|in:in,out',
        ]);

        $unit = Unit::findOrFail($data['unit']);
        abort_unless($request->user()->holdsUnit($unit), 403, 'دفتر الوارد والصادر متاح لمستخدم الجهة فقط.');

        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();

        $entries = $data['dir'] === 'in'
            ? LetterRecipient::query()->where('unit_id', $unit->id)
                ->whereHas('letter', fn ($q) => $q->whereIn('status', [LetterStatus::Sent->value, LetterStatus::Cancelled->value])->whereBetween('sent_at', [$from, $to]))
                ->with(['letter.senderUnit', 'letter.sender', 'letter.originUnit', 'receiver'])
                ->get()
                ->sortBy(fn ($r) => [$r->incoming_seq === null ? PHP_INT_MAX : $r->incoming_seq, $r->letter->sent_at])
                ->values()
            : Letter::query()->where('sender_unit_id', $unit->id)
                ->whereIn('status', [LetterStatus::Sent->value, LetterStatus::Cancelled->value])
                ->whereBetween('sent_at', [$from, $to])
                ->with(['recipients.unit', 'sender'])
                ->orderBy('outgoing_seq')->get();

        return view('reports.register', [
            'unit' => $unit, 'dir' => $data['dir'], 'entries' => $entries, 'from' => $from, 'to' => $to,
        ]);
    }
}
