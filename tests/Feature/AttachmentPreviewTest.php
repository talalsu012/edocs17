<?php

namespace Tests\Feature;

use App\Livewire\ShowLetter;
use App\Models\Unit;
use App\Models\User;
use App\Services\LetterService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** عرض المرفقات في نافذة منبثقة: PDF وصور داخل النظام، والباقي تنزيل. */
class AttachmentPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepare();
    }

    protected function prepare(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
        \Storage::fake('local');
    }

    public function test_preview_inline_for_visible_users_only(): void
    {
        $dean = User::where('email', 'eng.dean@edocs.test')->first();
        $svc = app(LetterService::class);
        $letter = $svc->saveDraft($dean, [
            'mode' => 'unit', 'unit_id' => Unit::where('code', 'ك ه')->value('id'), 'subject' => 'خطاب بمرفقات',
            'to' => [Unit::where('code', 'م ب ش')->value('id')],
        ], null, [
            UploadedFile::fake()->create('تقرير.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->image('صورة.png'),
            UploadedFile::fake()->create('جدول.xlsx', 10),
        ]);
        $svc->send($dean, $letter);
        [$pdf, $png, $xlsx] = $letter->attachments()->orderBy('id')->get()->all();

        $this->actingAs(User::where('email', 'hr@edocs.test')->first());
        Livewire::test(ShowLetter::class, ['letter' => $letter])
            ->assertSee('تقرير.pdf')->assertSee('role="dialog"', false);

        $r = $this->get(route('attachments.view', $pdf))->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $r->headers->get('Content-Disposition'));
        $this->assertSame('image/png', $this->get(route('attachments.view', $png))->assertOk()->headers->get('Content-Type'));
        $this->get(route('attachments.view', $xlsx))->assertStatus(415);
        $this->get(route('attachments.download', $xlsx))->assertOk();

        $this->actingAs(User::where('email', 'med.dean@edocs.test')->first());
        $this->get(route('attachments.view', $pdf))->assertForbidden();
    }
}
