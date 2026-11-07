<?php

namespace Database\Seeders;

use App\Enums\UnitType;
use App\Models\StaffAllowedTarget;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * الهيكل الإداري لجامعة كسلا (المكاتب والإدارات والعمادات) — مبدئي ويُعدَّل بعد اعتماد الهيكل الرسمي.
 * الكليات والأقسام مصدرها قاعدة الموظفين mang.
 *
 * كل عنصر: [الاسم, الرمز (للترقيم), النوع, صفة الرئيس, خيارات, [الأبناء]]
 */
class UnitSeeder extends Seeder
{
    /**
     * الكليات والأقسام تُستورد من قاعدة الموظفين mang (php artisan mang:import-units).
     * الكليات التجريبية تُستخدم فقط عند عدم توفر mang (بيئة الاختبار).
     */
    public bool $withSampleColleges = true;

    public function run(): void
    {
        $tree = [
            ['جامعة كسلا — مكتب مدير الجامعة', 'م ج', UnitType::University, 'مدير الجامعة', ['can_broadcast_all' => true], [

                ['مكتب نائب المدير', 'ن م', UnitType::Office, 'نائب المدير', [], [
                    ['أمانة الشؤون العلمية', 'أ ش ع', UnitType::Administration, 'أمين الشؤون العلمية', ['can_broadcast_all' => true], [
                        ['عمادة الدراسات العليا والبحث العلمي', 'ع د ع', UnitType::Deanship, 'العميد', [], []],
                        ['عمادة القبول والتسجيل', 'ع ق ت', UnitType::Deanship, 'العميد', [], []],
                        ['عمادة المكتبات', 'ع م ك', UnitType::Deanship, 'العميد', [], []],
                        ...($this->withSampleColleges ? $this->sampleColleges() : []),
                    ]],
                ]],

                ['مكتب وكيل الجامعة', 'و ج', UnitType::Office, 'وكيل الجامعة', [], [
                    ['إدارة الموارد البشرية', 'م ب ش', UnitType::Administration, 'مدير الإدارة', [], [
                        ['وحدة شؤون العاملين', 'ب ع', UnitType::Section, 'رئيس الوحدة', [], []],
                        ['وحدة التدريب', 'ب ت', UnitType::Section, 'رئيس الوحدة', [], []],
                    ]],
                    ['إدارة الشؤون المالية', 'ش م', UnitType::Administration, 'مدير الإدارة', [], [
                        ['وحدة الحسابات', 'م ح', UnitType::Section, 'رئيس الوحدة', [], []],
                        ['وحدة المرتبات', 'م ر', UnitType::Section, 'رئيس الوحدة', [], []],
                    ]],
                    ['إدارة الشؤون الهندسية', 'ش ه', UnitType::Administration, 'مدير الإدارة', [], []],
                    ['إدارة الخدمات', 'خ د', UnitType::Administration, 'مدير الإدارة', [], []],
                    ['إدارة المشتريات والمخازن', 'م خ', UnitType::Administration, 'مدير الإدارة', [], []],
                ]],

                ['عمادة شؤون الطلاب', 'ع ش ط', UnitType::Deanship, 'العميد', [], []],
                ['إدارة تقانة المعلومات', 'ت م', UnitType::Administration, 'مدير الإدارة', [], []],
                ['إدارة الشؤون القانونية', 'ش ق', UnitType::Administration, 'المستشار القانوني', [], []],
                ['إدارة العلاقات العامة والإعلام', 'ع ع', UnitType::Administration, 'مدير الإدارة', [], []],
                ['إدارة التخطيط والتطوير', 'ت ط', UnitType::Administration, 'مدير الإدارة', [], []],
                ['مركز ضمان الجودة', 'ض ج', UnitType::Center, 'مدير المركز', [], []],
                ['المراجعة الداخلية', 'م د', UnitType::Office, 'المراجع الداخلي', [], []],
            ]],
        ];

        foreach ($tree as $i => $node) {
            $this->createNode($node, null, $i);
        }

        // الجهات التي يحق للموظف العادي مراسلتها مباشرة
        foreach (['م ب ش', 'ش م', 'ع ش ط', 'ت م'] as $code) {
            StaffAllowedTarget::firstOrCreate(['unit_id' => Unit::where('code', $code)->value('id')]);
        }
    }

    /** كليات وأقسام تجريبية — بديل عن mang في بيئة الاختبار فقط. */
    protected function sampleColleges(): array
    {
        return [
            ['كلية الطب والعلوم الصحية', 'ك ط', UnitType::College, 'العميد', [], [
                ['قسم الطب الباطني', 'ط ب', UnitType::Department, 'رئيس القسم', [], []],
                ['قسم الجراحة', 'ط ج', UnitType::Department, 'رئيس القسم', [], []],
                ['قسم صحة المجتمع', 'ط ص', UnitType::Department, 'رئيس القسم', [], []],
            ]],
            ['كلية الهندسة', 'ك ه', UnitType::College, 'العميد', [], [
                ['قسم الهندسة الكهربائية', 'ه ك', UnitType::Department, 'رئيس القسم', [], []],
                ['قسم الهندسة المدنية', 'ه م', UnitType::Department, 'رئيس القسم', [], []],
            ]],
            ['كلية علوم الحاسوب وتقانة المعلومات', 'ك ح', UnitType::College, 'العميد', [], [
                ['قسم علوم الحاسوب', 'ح ع', UnitType::Department, 'رئيس القسم', [], []],
                ['قسم تقانة المعلومات', 'ح ت', UnitType::Department, 'رئيس القسم', [], []],
            ]],
            ['كلية التربية', 'ك ت', UnitType::College, 'العميد', [], [
                ['قسم اللغة العربية', 'ت ع', UnitType::Department, 'رئيس القسم', [], []],
                ['قسم اللغة الإنجليزية', 'ت ن', UnitType::Department, 'رئيس القسم', [], []],
            ]],
            ['كلية الاقتصاد والعلوم الإدارية', 'ك ق', UnitType::College, 'العميد', [], [
                ['قسم المحاسبة', 'ق ح', UnitType::Department, 'رئيس القسم', [], []],
                ['قسم إدارة الأعمال', 'ق د', UnitType::Department, 'رئيس القسم', [], []],
            ]],
            ['كلية الزراعة والموارد الطبيعية', 'ك ز', UnitType::College, 'العميد', [], []],
        ];
    }

    protected function createNode(array $node, ?Unit $parent, int $order): void
    {
        [$name, $code, $type, $headTitle, $options, $children] = $node;

        $unit = Unit::create(array_merge([
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'head_title' => $headTitle,
            'parent_id' => $parent?->id,
            'sort_order' => $order,
        ], $options));

        foreach ($children as $i => $child) {
            $this->createNode($child, $unit->refresh(), $i);
        }
    }
}
