@php
    $editing = $goal->exists;
    $categories = config('smartsave.goal_categories');
    $palette = collect(config('smartsave.goal_colors'))->mapWithKeys(fn ($c) => [$c => \App\Support\Ui::goal($c)])->all();
@endphp
<x-layouts.app :title="$editing ? 'Edit goal' : 'New goal'">
    <x-page-header :title="$editing ? 'Edit “'.$goal->name.'”' : 'Create a savings goal'"
                   :subtitle="$editing ? 'Changes here never alter recorded transactions.' : 'Give it a name, a target, and (optionally) a date.'" />

    <form method="POST" action="{{ $editing ? route('goals.update', $goal) : route('goals.store') }}" class="grid gap-6 lg:grid-cols-3"
          x-data="{
              category: @js(old('category', $goal->category)),
              icon: @js(old('icon', $goal->icon)),
              color: @js(old('color', $goal->color)),
              cats: @js($categories),
              palette: @js($palette),
              pick(c) { this.category = c; this.icon = this.cats[c].icon; this.color = this.cats[c].color; },
          }">
        @csrf
        @if ($editing) @method('PUT') @else <x-idempotency /> @endif

        <div class="card card-pad space-y-5 lg:col-span-2">
            <x-field name="name" label="Goal name" required>
                <input id="name" name="name" value="{{ old('name', $goal->name) }}" required maxlength="100" placeholder="e.g. Emergency fund" @class(['input', 'input-error' => $errors->has('name')])>
            </x-field>

            <fieldset>
                <legend class="label">Category</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($categories as $key => $cat)
                        <label class="cursor-pointer">
                            <input type="radio" name="category" value="{{ $key }}" class="peer sr-only" x-model="category" @change="pick('{{ $key }}')">
                            <span class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm transition peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-500/20 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 hover:border-slate-300 dark:border-slate-700 dark:peer-checked:bg-brand-500/10">
                                <x-goal-icon :icon="$cat['icon']" :color="$cat['color']" size="sm" />
                                <span class="truncate">{{ $cat['label'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('category')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="target_amount" label="Target amount" required>
                    <x-money-input name="target_amount" :value="$goal->target_amount" required />
                </x-field>
                <x-field name="target_date" label="Target date" hint="Optional. Used to work out how much to save each month.">
                    <input type="date" id="target_date" name="target_date" value="{{ old('target_date', $goal->target_date?->format('Y-m-d')) }}" min="{{ now()->addDay()->format('Y-m-d') }}" @class(['input', 'input-error' => $errors->has('target_date')])>
                </x-field>
                @unless ($editing)
                    <x-field name="starting_amount" label="Already saved (optional)" hint="Recorded once as an opening balance for this goal.">
                        <x-money-input name="starting_amount" />
                    </x-field>
                @endunless
                <x-field name="priority" label="Priority">
                    <select id="priority" name="priority" class="input">
                        @foreach (\App\Models\SavingsGoal::PRIORITIES as $k => $l)
                            <option value="{{ $k }}" @selected(old('priority', $goal->priority) === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            <x-field name="description" label="Description">
                <textarea id="description" name="description" rows="2" maxlength="2000" class="input" placeholder="What is this goal for?">{{ old('description', $goal->description) }}</textarea>
            </x-field>
        </div>

        <div class="space-y-6">
            <div class="card card-pad space-y-5">
                <h3 class="font-semibold">Look &amp; feel</h3>
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                    <span class="grid size-12 place-items-center rounded-xl transition" :class="palette[color]">
                        @foreach (config('smartsave.goal_icons') as $ic)
                            <span x-show="icon === '{{ $ic }}'" @if($ic !== $goal->icon) x-cloak @endif><x-icon :name="$ic" class="size-6" /></span>
                        @endforeach
                    </span>
                    <p class="text-sm text-slate-500">Preview</p>
                </div>
                <fieldset>
                    <legend class="label">Icon</legend>
                    <div class="grid grid-cols-6 gap-2">
                        @foreach (config('smartsave.goal_icons') as $ic)
                            <label class="cursor-pointer" title="{{ ucfirst(str_replace('-', ' ', $ic)) }}">
                                <input type="radio" name="icon" value="{{ $ic }}" x-model="icon" class="peer sr-only">
                                <span class="grid aspect-square place-items-center rounded-lg border border-slate-200 text-slate-500 transition peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 dark:border-slate-700 dark:peer-checked:bg-brand-500/10 dark:peer-checked:text-brand-300">
                                    <x-icon :name="$ic" class="size-5" /><span class="sr-only">{{ $ic }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="label">Colour</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (config('smartsave.goal_colors') as $c)
                            <label class="cursor-pointer" title="{{ ucfirst($c) }}">
                                <input type="radio" name="color" value="{{ $c }}" x-model="color" class="peer sr-only">
                                <span class="block size-8 rounded-full ring-offset-2 transition peer-checked:ring-2 peer-checked:ring-slate-900 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 dark:ring-offset-slate-900 dark:peer-checked:ring-white {{ \App\Support\Ui::goal($c, 'bar') }}"></span>
                                <span class="sr-only">{{ $c }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="card card-pad space-y-5">
                <h3 class="font-semibold">Contribution plan</h3>
                <x-field name="planned_contribution" label="Planned contribution" hint="How much you intend to add each period (optional).">
                    <x-money-input name="planned_contribution" :value="$goal->planned_contribution" />
                </x-field>
                <x-field name="contribution_frequency" label="Frequency">
                    <select id="contribution_frequency" name="contribution_frequency" class="input">
                        @foreach (config('smartsave.frequencies') as $k => $l)
                            <option value="{{ $k }}" @selected(old('contribution_frequency', $goal->contribution_frequency) === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="notes" label="Notes">
                    <textarea id="notes" name="notes" rows="2" maxlength="2000" class="input">{{ old('notes', $goal->notes) }}</textarea>
                </x-field>
            </div>

            <div class="flex gap-3">
                <a href="{{ $editing ? route('goals.show', $goal) : route('goals.index') }}" class="btn btn-secondary flex-1">Cancel</a>
                <button type="submit" class="btn btn-primary flex-1">{{ $editing ? 'Save changes' : 'Create goal' }}</button>
            </div>
        </div>
    </form>
</x-layouts.app>
