<div class="grid gap-2 sm:grid-cols-2">
    <input name="name" value="{{ $c?->name }}" class="input" placeholder="Name, e.g. GCash or BDO" required>
    <select name="kind" class="input">@foreach(\App\Models\Payment::METHODS as $k => $v)@continue($k === 'cash')<option value="{{ $k }}" @selected($c?->kind === $k)>{{ $v }}</option>@endforeach</select>
    <input name="account_name" value="{{ $c?->account_name }}" class="input" placeholder="Account name">
    <input name="account_number" value="{{ $c?->account_number }}" class="input" placeholder="Account / mobile number">
</div>
<textarea name="instructions" rows="2" class="input" placeholder="Instructions, e.g. Put the student's name in the message">{{ $c?->instructions }}</textarea>
<label class="block text-sm"><span class="label">QR code image (optional)</span><input type="file" name="qr" accept="image/*" class="text-sm"></label>
<label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($c?->is_active ?? true)> Show to families</label>
