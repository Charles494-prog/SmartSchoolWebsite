@props(['value'])
@php
    $map = [
        'approved' => 'bg-green-100 text-green-800', 'enrolled' => 'bg-green-100 text-green-800', 'present' => 'bg-green-100 text-green-800', 'active' => 'bg-green-100 text-green-800', 'promoted' => 'bg-green-100 text-green-800', 'finalized' => 'bg-green-100 text-green-800', 'submitted' => 'bg-blue-100 text-blue-800',
        'pending' => 'bg-amber-100 text-amber-800', 'for_review' => 'bg-blue-100 text-blue-800', 'late' => 'bg-amber-100 text-amber-800', 'draft' => 'bg-slate-200 text-slate-700', 'excused' => 'bg-blue-100 text-blue-800', 'returned' => 'bg-amber-100 text-amber-800',
        'rejected' => 'bg-red-100 text-red-800', 'absent' => 'bg-red-100 text-red-800', 'dropped' => 'bg-red-100 text-red-800', 'retained' => 'bg-red-100 text-red-800', 'transferred' => 'bg-slate-200 text-slate-700', 'transferred_out' => 'bg-slate-200 text-slate-700', 'graduated' => 'bg-purple-100 text-purple-800',
    ];
@endphp
<span class="badge {{ $map[$value] ?? 'bg-slate-100 text-slate-700' }}">{{ ucwords(str_replace('_', ' ', (string) $value)) }}</span>
