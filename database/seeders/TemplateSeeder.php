<?php

namespace Database\Seeders;

use App\Enums\TemplateAudience;
use App\Models\Template;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * النماذج الأولية من القسم 9 في التحليل. الحقول المعرّفة بـ auto تُملأ تلقائيًا من بيانات المستخدم.
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $hr = Unit::where('code', 'م ب ش')->value('id');
        $finance = Unit::where('code', 'ش م')->value('id');

        $auto = fn (string $name, string $label, string $source) => ['name' => $name, 'label' => $label, 'type' => 'text', 'auto' => $source];
        $field = fn (string $name, string $label, string $type = 'text', bool $required = true, array $extra = []) => array_merge(['name' => $name, 'label' => $label, 'type' => $type, 'required' => $required], $extra);

        $common = [
            $auto('جهة_المرسل', 'جهة المرسل', 'unit.name'),
            $auto('رقم_الصادر', 'الرقم الصادر', 'letter.outgoing_no'),
            $auto('التاريخ', 'التاريخ', 'today'),
        ];
        $employee = [
            $auto('اسم_الموظف', 'اسم الموظف', 'user.name'),
            $auto('الرقم_الوظيفي', 'الرقم الوظيفي', 'user.employee_no'),
        ];

        $templates = [
            [
                'code' => 'leave_request', 'name' => 'طلب إجازة', 'category' => 'الموارد البشرية',
                'subject_pattern' => 'طلب إجازة {{نوع_الإجازة}}',
                'allowed_for' => TemplateAudience::Staff, 'default_recipient_unit_id' => $hr,
                'fields' => [...$common, ...$employee,
                    $field('نوع_الإجازة', 'نوع الإجازة', 'select', true, ['options' => ['سنوية', 'مرضية', 'طارئة']]),
                    $field('من_تاريخ', 'من تاريخ', 'date'),
                    $field('إلى_تاريخ', 'إلى تاريخ', 'date'),
                    $field('العدد', 'عدد الأيام', 'number'),
                    $field('البديل', 'البديل'),
                    $field('توصية_المدير', 'رأي المدير المباشر', 'textarea', false),
                ],
                'body' => <<<'TXT'
                    السيد/ مدير إدارة الموارد البشرية
                    المحترم
                    السلام عليكم ورحمة الله وبركاته

                    الموضوع: طلب إجازة {{نوع_الإجازة}}

                    أرجو التكرم بالموافقة على منحي إجازة {{نوع_الإجازة}} لمدة {{العدد}} يومًا
                    اعتبارًا من {{من_تاريخ}} وحتى {{إلى_تاريخ}}، علمًا بأن {{البديل}} سيقوم بالعمل.

                    مقدم الطلب: {{اسم_الموظف}} — الرقم الوظيفي: {{الرقم_الوظيفي}}
                    رأي المدير المباشر: {{توصية_المدير}}
                    TXT,
            ],
            [
                'code' => 'experience_certificate', 'name' => 'طلب شهادة خبرة / تعريف', 'category' => 'الموارد البشرية',
                'subject_pattern' => 'طلب شهادة {{نوع_الشهادة}}',
                'allowed_for' => TemplateAudience::Staff, 'default_recipient_unit_id' => $hr,
                'fields' => [...$common, ...$employee,
                    $field('نوع_الشهادة', 'نوع الشهادة', 'select', true, ['options' => ['خبرة', 'تعريف']]),
                    $field('الغرض', 'الغرض'),
                    $field('الجهة_الموجه_إليها', 'الجهة الموجه إليها'),
                ],
                'body' => <<<'TXT'
                    السيد/ مدير إدارة الموارد البشرية
                    المحترم
                    السلام عليكم ورحمة الله وبركاته

                    الموضوع: طلب شهادة {{نوع_الشهادة}}

                    أرجو التكرم بإصدار شهادة {{نوع_الشهادة}} لي موجهة إلى {{الجهة_الموجه_إليها}}، وذلك لغرض {{الغرض}}.

                    مقدم الطلب: {{اسم_الموظف}} — الرقم الوظيفي: {{الرقم_الوظيفي}}
                    TXT,
            ],
            [
                'code' => 'transfer_request', 'name' => 'طلب نقل', 'category' => 'الموارد البشرية',
                'subject_pattern' => 'طلب نقل إلى {{الجهة_المطلوبة}}',
                'allowed_for' => TemplateAudience::Staff, 'default_recipient_unit_id' => $hr,
                'fields' => [...$common, ...$employee,
                    $field('الجهة_المطلوبة', 'الجهة المطلوبة'),
                    $field('الأسباب', 'الأسباب', 'textarea'),
                ],
                'body' => <<<'TXT'
                    السيد/ مدير إدارة الموارد البشرية
                    المحترم
                    السلام عليكم ورحمة الله وبركاته

                    الموضوع: طلب نقل

                    أرجو التكرم بالموافقة على نقلي إلى {{الجهة_المطلوبة}}، وذلك للأسباب التالية:
                    {{الأسباب}}

                    مقدم الطلب: {{اسم_الموظف}} — الرقم الوظيفي: {{الرقم_الوظيفي}}
                    TXT,
            ],
            [
                'code' => 'advance_request', 'name' => 'طلب سلفية', 'category' => 'الشؤون المالية',
                'subject_pattern' => 'طلب سلفية',
                'allowed_for' => TemplateAudience::Staff, 'default_recipient_unit_id' => $finance,
                'fields' => [...$common, ...$employee,
                    $field('المبلغ', 'المبلغ بالجنيه', 'number'),
                    $field('عدد_الأقساط', 'عدد الأقساط', 'number'),
                ],
                'body' => <<<'TXT'
                    السيد/ مدير إدارة الشؤون المالية
                    المحترم
                    السلام عليكم ورحمة الله وبركاته

                    الموضوع: طلب سلفية

                    أرجو التكرم بالموافقة على منحي سلفية بمبلغ {{المبلغ}} جنيه سوداني، تُسدَّد على {{عدد_الأقساط}} قسطًا شهريًا.

                    مقدم الطلب: {{اسم_الموظف}} — الرقم الوظيفي: {{الرقم_الوظيفي}}
                    TXT,
            ],
            [
                'code' => 'general_letter', 'name' => 'خطاب رسمي عام', 'category' => 'عام',
                'subject_pattern' => '{{الموضوع}}',
                'allowed_for' => TemplateAudience::Unit, 'default_recipient_unit_id' => null,
                'fields' => [...$common,
                    $field('المخاطب', 'السيد/ (صفة المخاطب)'),
                    $field('الموضوع', 'الموضوع'),
                    $field('النص', 'النص', 'textarea'),
                ],
                'body' => <<<'TXT'
                    السيد/ {{المخاطب}}
                    المحترم
                    السلام عليكم ورحمة الله وبركاته

                    الموضوع: {{الموضوع}}

                    {{النص}}

                    وتفضلوا بقبول فائق الاحترام،،،
                    TXT,
            ],
            [
                'code' => 'circular', 'name' => 'تعميم إداري', 'category' => 'تعاميم',
                'subject_pattern' => 'تعميم بشأن {{الموضوع}}',
                'allowed_for' => TemplateAudience::Unit, 'default_recipient_unit_id' => null,
                'fields' => [...$common,
                    $field('الموضوع', 'الموضوع'),
                    $field('النص', 'النص', 'textarea'),
                    $field('تاريخ_السريان', 'تاريخ السريان', 'date'),
                ],
                'body' => <<<'TXT'
                    تعميم إداري

                    الموضوع: {{الموضوع}}

                    {{النص}}

                    يسري هذا التعميم اعتبارًا من {{تاريخ_السريان}}.
                    TXT,
            ],
            [
                'code' => 'meeting_invitation', 'name' => 'دعوة اجتماع', 'category' => 'عام',
                'subject_pattern' => 'دعوة لاجتماع {{عنوان_الاجتماع}}',
                'allowed_for' => TemplateAudience::Unit, 'default_recipient_unit_id' => null,
                'fields' => [...$common,
                    $field('عنوان_الاجتماع', 'عنوان الاجتماع'),
                    $field('تاريخ_الاجتماع', 'التاريخ', 'date'),
                    $field('الزمن', 'الزمن', 'time'),
                    $field('المكان', 'المكان'),
                    $field('جدول_الأعمال', 'جدول الأعمال', 'textarea'),
                ],
                'body' => <<<'TXT'
                    السادة/ المذكورين أدناه
                    المحترمين
                    السلام عليكم ورحمة الله وبركاته

                    الموضوع: دعوة لاجتماع {{عنوان_الاجتماع}}

                    نتشرف بدعوتكم لحضور الاجتماع المقرر عقده يوم {{تاريخ_الاجتماع}} الساعة {{الزمن}} بـ{{المكان}}، وذلك لمناقشة:
                    {{جدول_الأعمال}}

                    نأمل التكرم بالحضور في الموعد المحدد.
                    TXT,
            ],
            [
                'code' => 'approval_request', 'name' => 'طلب موافقة / تصديق', 'category' => 'عام',
                'subject_pattern' => 'طلب موافقة على {{الموضوع}}',
                'allowed_for' => TemplateAudience::Unit, 'default_recipient_unit_id' => null,
                'fields' => [...$common,
                    $field('الموضوع', 'الموضوع'),
                    $field('المبررات', 'المبررات', 'textarea'),
                    $field('التكلفة', 'التكلفة التقديرية بالجنيه', 'number', false),
                ],
                'body' => <<<'TXT'
                    السيد/ (الجهة الأعلى)
                    المحترم
                    السلام عليكم ورحمة الله وبركاته

                    الموضوع: طلب موافقة على {{الموضوع}}

                    نرجو التكرم بالموافقة على {{الموضوع}}، وذلك للمبررات التالية:
                    {{المبررات}}

                    التكلفة التقديرية: {{التكلفة}} جنيه سوداني.
                    TXT,
            ],
            [
                'code' => 'reply', 'name' => 'إفادة / رد', 'category' => 'عام',
                'subject_pattern' => 'إفادة بخصوص خطابكم رقم {{رقم_الخطاب_المردود_عليه}}',
                'allowed_for' => TemplateAudience::Unit, 'default_recipient_unit_id' => null,
                'fields' => [...$common,
                    $field('رقم_الخطاب_المردود_عليه', 'رقم الخطاب المردود عليه'),
                    $field('النص', 'النص', 'textarea'),
                ],
                'body' => <<<'TXT'
                    بالإشارة إلى خطابكم رقم {{رقم_الخطاب_المردود_عليه}}، نفيدكم بالآتي:

                    {{النص}}

                    وتفضلوا بقبول فائق الاحترام،،،
                    TXT,
            ],
        ];

        foreach ($templates as $t) {
            Template::create([
                'code' => $t['code'],
                'version' => 1,
                'name' => $t['name'],
                'category' => $t['category'],
                'subject_pattern' => $t['subject_pattern'],
                'body_with_fields' => $t['body'],
                'fields_json' => $t['fields'],
                'default_recipient_unit_id' => $t['default_recipient_unit_id'],
                'allowed_for' => $t['allowed_for'],
            ]);
        }
    }
}
