<?php 
$location = $crud->getModel();

if(isset($id) && $id){
    $location = $location::find($id)->getAttributes();
}
?>
    <div class="form-group col-md-12">
        <label>{{ $field['label'] }}</label>
        <div class="input-group">          
            <div class="input-group-addon">
                <i class="fa fa-map-marker"></i>
            </div>
            <input id="search_address" class="form-control" type="text" placeholder="Enter a location" />
            <div id="geolocate" class="input-group-addon btn btn-primary">
                <i class="fa {{$field['geolocate_icon']}}"></i>
            </div>
        </div>
    </div>

    <div class="form-group col-md-12">
        <div id="map-canvas" style="{{$field['map_style']}}"></div>  
    </div>

    @foreach ($field['components'] as $component)

        <div class="{{ $component['wrapper'] }}">
            <label>{!! $component['label'] !!}</label>
            <input 
                type="text" 
                id="{{ $component['name'] }}"
                class="form-control"
                name="{{ $component['name'] }}"
                value="{{ old($component['name'], isset($location[$component['name']]) ? $location[$component['name']] : null) }}"
            >
        </div>

    @endforeach

{{-- ########################################## --}}
{{-- Extra CSS and JS for this particular field --}}
{{-- If a field type is shown multiple times on a form, the CSS and JS will only be loaded once --}}
@if ($crud->checkIfFieldIsFirstOfItsType($field, $fields))

    {{-- FIELD CSS - will be loaded in the after_styles section --}}
    @push('crud_fields_styles')
	    {{-- YOUR CSS HERE --}}
    @endpush

    {{-- FIELD JS - will be loaded in the after_scripts section --}}
    @push('crud_fields_scripts')
    	{{-- YOUR JS HERE --}}
        <script src="https://maps.googleapis.com/maps/api/js?v=3&libraries=places&key={{$field['google_api_key']}}"></script>

        <script>
        $(document).ready(function() {

            // overwrite BackPack-CRUD submit on Enter
            $(window).keydown(function(event){
                if(event.keyCode == 13) {
                    event.preventDefault();
                    return false;
                }
            });

            function initialize() {
                var marker;
                var default_zoom = {{$field['default_zoom']}};
                var geolocate_icon = "{{$field['geolocate_icon']}}";
                var latlong = document.getElementById('latlng').value; // get latlng value if any
                latlong = latlong.split(',');
                var latlng = (latlong == '') ? new google.maps.LatLng(3.138675,101.6167769) : new google.maps.LatLng(latlong[0], latlong[1]);
                var geocoder = new google.maps.Geocoder();
                var mapOptions = {
                    center: latlng,
                    zoom: 7,
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    draggableCursor: "pointer",
                    streetViewControl: false
                };

                // set search_address with formatted_address if latlng has value
                (latlong == '') ? '' : setGeoCoder(latlng);

                // set marker icon
                var marker_icon = "{{$field['marker_icon']}}";
                (marker_icon == '') ? null : marker_icon;
                
                var map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);
            
                var markers = new google.maps.Marker({
                    position: latlng,
                    map: map,
                    icon : marker_icon
                });
            
                google.maps.event.addListener(map, "click", function (location) {
                    //map.setCenter(location.latLng); // set location to map's center each time map is clicked
                    setLatLong(location.latLng.lat(), location.latLng.lng());

                    marker.setMap(null);
                    markers.setMap(null);
                
                    marker = new google.maps.Marker({
                        position: location.latLng,
                        map: map,
                        icon : marker_icon
                    });
                    marker.setPosition(location.latLng);
                    setGeoCoder(location.latLng);
                });

                var input = (document.getElementById('search_address'));

                var autocomplete = new google.maps.places.Autocomplete(input);
                autocomplete.bindTo('bounds', map);
                autocomplete.setTypes([]);

                var infowindow = new google.maps.InfoWindow();
                marker = new google.maps.Marker({
                    map: map,
                    anchorPoint: new google.maps.Point(0, -29),
                    icon : marker_icon
                });

                google.maps.event.addListener(autocomplete, 'place_changed', function() {
                    //infowindow.close();
                    marker.setVisible(true);
                    markers.setMap(null);
                    var place = autocomplete.getPlace();

                    if (!place.geometry) return;

                    // If the place has a geometry, then preview it on the map.
                    if (place.geometry.viewport) {
                        map.fitBounds(place.geometry.viewport);
                    } else {
                        map.setCenter(place.geometry.location);
                        map.setZoom(default_zoom);
                    }
                    marker.setIcon(marker_icon);
                    map.setZoom(default_zoom);
                    marker.setPosition(place.geometry.location);
                    marker.setVisible(true);
                    setLatLong(place.geometry.location.lat(), place.geometry.location.lng());
                    setGeoCoder(place.geometry.location);
                });

                document.getElementById('geolocate').onclick=function(){

                    $('.'+geolocate_icon).addClass('fa-spin'); // add spinning animation for no reason..

                    if(navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(function(position) {

                            var pos = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);

                            map.setCenter(pos);
                            map.setZoom(default_zoom);
                            marker.setPosition(pos);

                            setGeoCoder(pos);
                            setLatLong(position.coords.latitude, position.coords.longitude);

                            $('.'+geolocate_icon).removeClass('fa-spin');

                        }, function() {
                            handleNoGeolocation(true);
                        });
                    } else {
                        // Browser doesn't support Geolocation
                        handleNoGeolocation(false);
                        $('.'+geolocate_icon).removeClass('fa-spin');
                    }
                };


                function setLatLong(lat, long) {
                    document.getElementById('latlng').value=lat+', '+long;
                }

                function setGeoCoder(pos) {
                    geocoder.geocode({'location': pos}, function(results, status) {
                        if (status == google.maps.GeocoderStatus.OK) {
                            if (results[0]) {
                                document.getElementById('search_address').value=results[0].formatted_address;
                                update_address(results); // update address
                            } else {
                                document.getElementById('search_address').value='';
                            }
                        } else {
                            document.getElementById('search_address').value='';
                        }
                    });
                }

                function update_address(results) {
                    reset(); //reset before continue
                    for (var ii = 0; ii < results[0].address_components.length; ii++) {
                        var street_number = route = street = city = state = zipcode = country = formatted_address = '';
                        var types = results[0].address_components[ii].types.join(",");
                        
                        if(types == "premise"){
                            premise = results[0].address_components[ii].long_name;
                            document.getElementById('street').value += premise+', ';
                        }

                        if (types == "street_number") {
                            street_number = results[0].address_components[ii].long_name;
                            document.getElementById('street').value += street_number+', ';
                        }

                        if (types == "route" || types == "point_of_interest,establishment") {
                            route = results[0].address_components[ii].long_name;
                            document.getElementById('street').value += route+', ';
                        }
                        
                        if (types == "sublocality_level_1" || types == "political,sublocality,sublocality_level_1") {
                            street = results[0].address_components[ii].long_name;
                            document.getElementById('street').value += street+', ';
                        }

                        if (types == "sublocality,political" || types == "locality,political" || types == "neighborhood,political" || types == "administrative_area_level_3,political") {
                            city = (city === '' || types == "locality,political") ? results[0].address_components[ii].long_name : city;
                            document.getElementById('city').value = city;
                        }

                        if (types == "administrative_area_level_1,political") {
                            state = results[0].address_components[ii].long_name;
                            document.getElementById('state').value = state;
                        }

                        if (types == "postal_code" || types == "postal_code_prefix,postal_code") {
                            zipcode = results[0].address_components[ii].long_name;
                            document.getElementById('zipcode').value = zipcode;
                        }

                        if (types == "country,political") {
                            country = results[0].address_components[ii].long_name;
                            document.getElementById('country').value = country;
                        }
                    }
                
                }

                function reset(){
                    document.getElementById('street').value = "";
                    document.getElementById('zipcode').value = "";
                    document.getElementById('city').value = "";
                    document.getElementById('state').value = "";
                    document.getElementById('country').value = "";
                }

                function handleNoGeolocation(databool){
                    (databool) ? true : alert('Browser doesn\'t support Geolocation');
                }
            }

            google.maps.event.addDomListener(window, 'load', initialize);

        });
        </script>

    @endpush

@endif
{{-- End of Extra CSS and JS --}}
{{-- ########################################## --}}
