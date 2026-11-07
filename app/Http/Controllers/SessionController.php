<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Support\ActiveUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SessionController extends Controller
{
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function switchUnit(Request $request)
    {
        ActiveUnit::set($request->user(), (int) $request->input('unit_id'));

        return redirect()->route('mail.box', 'inbox');
    }

    /** تنزيل مرفق — فقط لمن يرى الخطاب. */
    public function attachment(Request $request, Attachment $attachment)
    {
        abort_unless($attachment->letter->isVisibleTo($request->user()), 403);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    /** عرض المرفق داخل النافذة المنبثقة (PDF وصور) — نفس شرط الرؤية. */
    public function viewAttachment(Request $request, Attachment $attachment)
    {
        abort_unless($attachment->letter->isVisibleTo($request->user()), 403);
        abort_unless($attachment->isPreviewable(), 415);

        return Storage::disk('local')->response($attachment->file_path, $attachment->file_name, [
            'Content-Type' => $attachment->previewMime(),
            'Content-Disposition' => 'inline; filename*=UTF-8\'\''.rawurlencode($attachment->file_name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
