<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterEvent;
use App\Models\Unit;
use App\Services\AssignmentService;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    /** طباعة الخطاب بترويسة الجامعة ورمز QR (حفظ PDF من المتصفح). */
    public function letter(Request $request, Letter $letter)
    {
        abort_unless($letter->isVisibleTo($request->user()), 403);
        abort_unless($letter->isSent(), 404, 'لا يمكن طباعة مسودة.');

        $letter->load(['senderUnit', 'originUnit', 'sender', 'approver', 'recipients.unit', 'recipients.user', 'attachments', 'parent']);
        $letter->logEvent(LetterEvent::PRINTED, $request->user());

        return view('print.letter', ['letter' => $letter, 'autoprint' => $request->boolean('auto', true)]);
    }

    /** صفحة التحقق العامة التي يفتحها رمز QR. */
    public function verify(Letter $letter, string $code)
    {
        $valid = $letter->isSent() && hash_equals($letter->verificationCode(), $code);
        $letter->load(['senderUnit', 'originUnit', 'recipients.unit']);

        return response()->view('print.verify', ['letter' => $letter, 'valid' => $valid], $valid ? 200 : 404);
    }

    public function handover(Unit $unit, AssignmentService $service)
    {
        return view('admin.handover', ['unit' => $unit->load('activeAssignment.user'), 'report' => $service->handover($unit)]);
    }
}
