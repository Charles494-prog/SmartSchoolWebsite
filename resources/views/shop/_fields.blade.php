<input name="name" value="{{ $i?->name }}" class="input" placeholder="Name, e.g. PE uniform" required>
<div class="grid grid-cols-2 gap-2">
    <select name="category" class="input">@foreach(\App\Models\ShopItem::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected($i?->category === $k)>{{ $v }}</option>@endforeach</select>
    <input name="price" type="number" step="0.01" min="0" value="{{ $i?->price }}" class="input" placeholder="Price ₱" required>
    <input name="sizes" value="{{ $i?->sizes ? implode(', ', $i->sizes) : '' }}" class="input" placeholder="Sizes: S, M, L (optional)">
    <input name="stock" type="number" min="0" value="{{ $i?->stock }}" class="input" placeholder="Stock (blank = no limit)">
</div>
<textarea name="description" rows="2" class="input" placeholder="Description (optional)">{{ $i?->description }}</textarea>
<label class="block text-sm"><span class="label">Photo</span><input type="file" name="image" accept="image/*" class="text-sm"></label>
<label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($i?->is_active ?? true)> Show in the bookstore</label>
