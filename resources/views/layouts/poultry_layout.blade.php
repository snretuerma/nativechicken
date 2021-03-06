<!DOCTYPE html>
<html>
<head>
    <!--Import Google Icon Font-->
    <link href="{{ URL::asset('https://fonts.googleapis.com/icon?family=Material+Icons') }}" rel="stylesheet">
    <!--Import materialize.css-->
    <link type="text/css" rel="stylesheet" href="/thirdparty/fontawesome-free-5.6.0-web/css/all.min.css">
    <link type="text/css" rel="stylesheet" href="/thirdparty/materialize/css/materialize.min.css"  media="screen,projection"/>
    <link type="text/css" rel="stylesheet" href="/css/application_materialize.css"  media="screen,projection"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.3.0/css/font-awesome.css" rel="stylesheet"  type='text/css'>
    <link href="https://afeld.github.io/emoji-css/emoji.css" rel="stylesheet">
    @yield('customcss')
    <!--Let browser know website is optimized for mobile-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" >
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PAB-IS | @yield('title')</title>
    <style>
        .no-format{
            all: unset;
            font-family: 'Raleway', sans-serif; 
        }
        .farm-name{
            font-family: 'Raleway', sans-serif; 
            text-indent: 0em;
            text-align: center;
            font-weight: bold;
        }
        .pagination li.active {
            background-color : #546e7a;
        }
        .custom-card-title{
            border-bottom: 2px solid #546e7a;
            padding-bottom: 8px;
            border-bottom-right-radius: 5px;
            border-bottom-left-radius: 5px;
        }

        .left-column-divider {
            border-left: 1.5px solid #546e7a;
        }
        .right-column-divider {
            border-right: 1.5px solid #546e7a;
        }
        .spinner-layer{
            border-color: #546e7a;
        }
        
        .switch label input[type=checkbox]:checked+.lever:after{
            background-color: #546e7a;
        }
        .switch label input[type=checkbox]:checked+.lever {
            background-color: #91a5a3;
        }
        .note_div {
            background-color: #FFDAB9;
        }
        .warning_div {
            background-color: #FFB6C1;
        }
        
        .tabs .tab a{
            color:#000;
        } /*Black color to the text*/

        .tabs .tab a:hover {
            background-color:#eee;
            color:#000;
        } /*Text color on hover*/

        .tabs .tab a.active {
            color:#000;
        } /*Background and text color when a tab is active*/

        .tabs .indicator {
            background-color:#546e7a;
        } /*Color of underline*/
        
        /* label color */
        label {
            color: #000;
        }
        .input-field label {
            color: #000;
        }
        /* label focus color */
        .input-field input[type=text]:focus + label {
            color: #607d8b;
        }
        [type="radio"].with-gap:checked+label:after {
            background-color: #546e7a !important;
        }

        [type="radio"].with-gap:checked+label:before,
        [type="radio"].with-gap:checked+label:after {
            border: 2px solid #546e7a !important;
        }
        .input-field input[type=number]:focus {
            border-bottom: 1px solid #607d8b;
            box-shadow: 0 1px 0 0 #546e7a;
        }
        .inline_error_message {
            color: #c62828;
        }   
        .inline_warning_message {
            color: #ff7043;
        }
        
    </style>
</head>

<body>
    {{-- Side navigation --}}
    <ul id="slide-out" class="side-nav fixed blue-grey lighten-2">
        <li>
            <div class="user-view">
                <div class="background blue-grey darken-1">

                </div>
                @if (Auth::user()->picture!==null)
                    <img class="circle" src="{{Auth::user()->picture}}" alt="farmer">
                @else
                    <img class="circle" src="https://image.ibb.co/nqiNCA/logo-default.png" alt="farmer">
                @endif
                
                <span class="white-text name">{{Auth::user()->name}}</span>
                <span class="white-text email">{{Auth::user()->email}}</span>
            </div>
        </li>
        <li><a class="subheader">Menu</a></li>
        <li class="no-padding">
            <ul class="collapsible">
                <li>
                    <a href="{{route('farm.index')}}" class="collapsible-header" class="waves-effect">Dashboard<i class="fas fa-home"></i></a>
                </li>
            </ul>
        </li>
        @if (Auth::user()->getFarm()->batching_week != -1)
        <li class="no-padding">
                <ul class="collapsible">
                    <li>
                        <a href="{{route('farm.pens')}}" class="collapsible-header">Pens<i class="fas fa-boxes"></i></a>
                    </li>
                </ul>
            </li>
            <li class="no-padding">
                <ul class="collapsible">
                    <li>
                        <a href="{{route('farms.generation_lines_page')}}" class="collapsible-header" class="waves-effect">Generations & Lines<i class="fas fa-dna"></i></a>
                    </li>
                </ul>
            </li>
            <li class="no-padding">
                <ul class="collapsible">
                    <li>
                        <a href="{{route('farm.family_records')}}" class="collapsible-header" class="waves-effect">Families<i class="fas fa-list"></i></a>
                    </li>
                </ul>
            </li>
            <li class="no-padding">
                <ul class="collapsible collapsible-accordion">
                    <li>
                        <a href="{{route('farm.chicken.breeder.add_breeder')}}" class="collapsible-header">Breeder<i class="fas fa-certificate"></i></a>
                    </li>
                </ul>
            </li>
            <li class="no-padding">
                <ul class="collapsible collapsible-accordion">
                    <li>
                    <a href="{{route('farm.chicken.replacemnt.replacement_add')}}" class="collapsible-header">Growers & Replacement<i class="fas fa-feather"></i></a>
                    </li>
                </ul>
            </li>
            <li class="no-padding">
                <ul class="collapsible collapsible-accordion">
                    <li>
                    <a href="{{route('farm.chicken.broodergrower.broodergrower_add')}}" class="collapsible-header">Brooders<i class="fas fa-crow"></i></a>
                    </li>
                </ul>
            </li>
            <li class="no-padding">
                <ul class="collapsible collapsible-accordion">
                    <li>
                        <a href="javascript:void(0)" class="collapsible-header" class="waves-effect">Farm Summary<i class="fas fa-chart-bar"></i></a>
                         <div class="collapsible-body">
                            <ul>
                                <li><a href="{{route('farm.generation_records')}}">Generation</a></li>
                                <li><a href="{{route('farm.farm_records')}}">Family</a></li>
                            </ul>
                        </div> 
                    </li>
                </ul>
            </li>
            <li class="no-padding">
                <ul class="collapsible">
                    <li>
                        <a href="{{route('farm.farm_settings')}}" class="collapsible-header" class="waves-effect">Farm Info and Notifictions<i class="fas fa-info"></i></a>
                    </li>
                </ul>
            </li>
        @endif
        <li class="no-padding">
            <ul class="collapsible">
                <li>
                    <a href="{{route('logout')}}" class="collapsible-header" class="waves-effect">Logout<i class="fas fa-sign-out-alt"></i></a>
                </li>
            </ul>
        </li>
    </ul>
    {{-- Top navigation --}}
    <nav id="application_top_nav" class="blue-grey darken-1">
        <div class="nav-wrapper">
            <a href="#" data-activates="slide-out" class="button-collapse hide-on-large-only"><i class="material-icons">menu</i></a>
            <a href="{{route('farm.index')}}" class="brand-logo center"><img id="poultry-logo" src="https://image.ibb.co/dBHtKq/logo-poultry.png" alt="poultry-logo" height="100px" width="250px"/></a>
        </div>
    </nav>
    <main>
        <div class="container" id="app">
            @yield('content')
        </div>
    </main>
    <script type="text/javascript" src="/js/app.js"></script>
    <script type="text/javascript" src="/thirdparty/jquery-3.3.1.js"></script>
    <script type="text/javascript" src="/thirdparty/materialize/js/materialize.min.js"></script>
    <script type="text/javascript" src="/js/application_materialize.js"></script>
    @yield('customscripts')
</body>
</html>
