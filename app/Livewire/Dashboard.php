<?php

namespace App\Livewire;

use App\Models\Unit;
use App\Services\Stats;
use App\Support\ActiveUnit;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('لوحة المؤشرات')]
class Dashboard extends Component
{
    #[Url(except: 30)]
    public int $days = 30;

    #[Url(as: 'unit')]
    public ?int $unitId = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->isUnitUser() || $user->hasPermission('reports.view'), 403);

        if (! $this->unitId || ! $this->canView(Unit::find($this->unitId))) {
            $this->unitId = ActiveUnit::get()?->id ?? Unit::roots()->value('id');
        }
    }

    /** مستخدم الجهة يرى فرعه؛ صاحب صلاحية التقارير يرى أي جهة. */
    protected function canView(?Unit $unit): bool
    {
        if (! $unit) {
            return false;
        }
        $user = auth()->user();

        return $user->hasPermission('reports.view')
            || $user->currentUnits()->contains(fn ($u) => str_starts_with($unit->path, $u->path));
    }

    #[Computed]
    public function unit(): Unit
    {
        $unit = Unit::findOrFail($this->unitId);
        abort_unless($this->canView($unit), 403);

        return $unit;
    }

    public function drill(int $id): void
    {
        if ($this->canView(Unit::find($id))) {
            $this->unitId = $id;
            unset($this->unit);
        }
    }

    public function up(): void
    {
        $parent = $this->unit->parent;
        if ($parent && $this->canView($parent)) {
            $this->drill($parent->id);
        }
    }

    public function render(Stats $stats)
    {
        $days = in_array($this->days, [7, 30, 90, 365], true) ? $this->days : 30;
        $from = today()->subDays($days - 1);
        $to = today();
        $unit = $this->unit;

        $data = $stats->breakdown($unit, $from, $to);
        $daily = $stats->daily($unit->subtree()->pluck('id')->all(), $days > 90 ? today()->subDays(89) : $from, $to);

        return view('livewire.dashboard', [
            'unit' => $unit,
            'rows' => $data['rows'],
            'total' => $data['total'],
            'daily' => $daily,
            'from' => $from,
            'canUp' => $unit->parent && $this->canView($unit->parent),
            'isHolder' => auth()->user()->holdsUnit($unit),
        ]);
    }
}
