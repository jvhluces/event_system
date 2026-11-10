let timer;
const locationInput = document.getElementById("location");
const suggestionsBox = document.getElementById("suggestions");

locationInput.addEventListener("input", function(){
    clearTimeout(timer);
    let query = this.value;
    if(query.length > 2){
        timer = setTimeout(() => {
            fetch("https://photon.komoot.io/api/?q=" + encodeURIComponent(query) + "&limit=5")
            .then(response => response.json())
            .then(data => {
                let suggestions = "";
                if(data.features && data.features.length > 0){
                    data.features.forEach(place => {
                        let display = place.properties.name;
                        if(place.properties.city) display += ", " + place.properties.city;
                        if(place.properties.country) display += ", " + place.properties.country;

                        suggestions += "<div style='padding:8px; cursor:pointer; border-bottom:1px solid #ddd;' " +
                                       "onclick=\"locationInput.value='" + display + "'; suggestionsBox.innerHTML='';\">" 
                                       + display + "</div>";
                    });
                } else {
                    suggestions = "<div style='padding:8px; color:#888;'>No matches found</div>";
                }
                suggestionsBox.innerHTML = suggestions;
            })
            .catch(err => {
                suggestionsBox.innerHTML = "<div style='padding:8px; color:red;'>Error loading suggestions</div>";
            });
        }, 400); 
    } else {
        suggestionsBox.innerHTML = "";
    }
});


document.addEventListener("click", function(e){
    if(e.target !== locationInput && !suggestionsBox.contains(e.target)){
        suggestionsBox.innerHTML = "";
    }
});