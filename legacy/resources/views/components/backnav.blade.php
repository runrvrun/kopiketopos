<nav class="backnav">
<a href="{{ $backlink ?? 'javascript:history.back()'}}"><i class="fa fa-angle-left"></i></a> {{ $title ?? 'Back' }}
@if($button == 'add')
<div class="backnav-button"><a href="{{ url('product/create') }}"><i class="fa fa-plus"></i></a></div>
@endif
</nav>