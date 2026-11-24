<form method="post" action="{{ route('finance.assess', $e) }}" class="mt-4 space-y-4">
    @csrf
    <x-field name="payment_plan" label="Payment plan" type="select" :options="$plans" :value="$a?->payment_plan" required />
    <div>
        <div class="label">Discounts & scholarships</div>
        <div class="space-y-2">
            @forelse($discounts as $d)
                <label class="flex items-center gap-3 rounded-xl p-2.5 text-sm ring-1 ring-slate-200 hover:bg-white dark:hover:bg-white/5">
                    <input type="checkbox" name="discount_ids[]" value="{{ $d->id }}" class="rounded" @checked($a && $a->discounts->contains('discount_id', $d->id))>
                    <span class="flex-1">{{ $d->name }} @if($d->is_scholarship)<span class="badge ml-1 bg-violet-100 text-violet-700">Scholarship</span>@endif</span>
                    <span class="text-slate-500">{{ $d->type === 'percent' ? rtrim(rtrim(number_format($d->value, 2), '0'), '.').'%' : '₱'.number_format($d->value, 2) }}{{ $d->tuition_only ? ' of tuition' : '' }}</span>
                </label>
            @empty <p class="muted">None yet.</p> @endforelse
        </div>
        @if(auth()->user()->hasRole('admin', 'cashier'))<a href="{{ route('crud.create', 'discounts') }}" class="link mt-2 inline-block text-sm">+ New discount or scholarship</a> · <a href="{{ route('crud.index', 'payment-plans') }}" class="link text-sm">Manage payment plans</a>@endif
    </div>
    <p class="text-xs text-slate-500">Saving rebuilds the payment schedule from the plan. Payments already received are kept.</p>
    <button class="btn btn-primary">{{ $a ? 'Save and rebuild schedule' : 'Set up fees' }}</button>
</form>
