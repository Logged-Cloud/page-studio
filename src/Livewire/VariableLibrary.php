<?php

namespace LoggedCloud\PageStudio\Livewire;

use Livewire\Component;
use LoggedCloud\PageStudio\Concerns\AuthorizesPageStudio;
use LoggedCloud\PageStudio\Models\Variable;

class VariableLibrary extends Component
{
    use AuthorizesPageStudio;

    public function mount(): void
    {
        $this->authorizePageStudio();
    }
    public string $search = '';

    public function delete(int $id): void
    {
        $var = Variable::findOrFail($id);
        if ($var->segments()->exists()) {
            $this->addError('delete', "Variable {$var->name} is still in use by routes.");
            return;
        }
        $var->delete();
    }

    public function render()
    {
        $variables = Variable::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->withCount('segments')
            ->get();

        return view('page-studio::livewire.variable-library', compact('variables'));
    }
}
