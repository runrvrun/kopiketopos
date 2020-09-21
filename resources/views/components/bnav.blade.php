    <nav class="bnav">
        <div class="row">
            <div class="col-2 offset-1 {{ ($current=='home')? 'active':'' }}"><a href="{{ route('home') }}"><i class="fa fa-home"></i>Home</a></div>
            <div class="col-2 {{ ($current=='ongoing')? 'active':'' }}"><a href="{{url('transaction/ongoing')}}"><i class="fa fa-hourglass"></i>Ongoing</a></div>
            <div class="col-2"><a href="{{route('transaction.create')}}"><i class="fa fa-plus-circle primarycolordark" style="font-size:35pt;margin-top:-7px"></i></a></div>
            <div class="col-2 {{ ($current=='history')? 'active':'' }}"><a href="{{url('transaction/history')}}"><i class="fa fa-list"></i>History</a></div>
            <div class="col-2 {{ ($current=='more')? 'active':'' }}"><a href="{{ url('more') }}"><i class="fa fa-ellipsis-h"></i></a></div>
        </div>
    </nav>