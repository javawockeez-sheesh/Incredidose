const params = new URLSearchParams(window.location.search);
const prescriptionid = params.get("prescriptionid");
const patientid = params.get("patientid");

let sortVal = 0;

//Buttons
document.addEventListener("DOMContentLoaded", () => {
  phpDisplayAll(prescriptionid);

  const backButton = document.querySelector("#back");
  backButton.addEventListener("click", () => {
      window.location.href = `index.html`;
  });

  const addButton = document.querySelector("#add");
  addButton.addEventListener("click", function(event) {
	   const popup = document.getElementsByClassName("container");
	   let rect = event.target.getBoundingClientRect();

	   popup[0].style.left = (rect.left * .7) + window.scrollX + "px";
	   popup[0].style.top = (rect.bottom * .9) + window.scrollY + "px";

	   popup[0].style.visibility = "visible";
  });


  const sortButton = document.querySelector("#sort");
  sortButton.addEventListener("click", (event) => {
    if(sortVal == 0){
      event.target.innerHTML = "Sort Desc ↓";
      sortVal = 1;
    }else{
      event.target.innerHTML = "Sort Asc ↑";
      sortVal = 0;
    }
    phpDisplayAll(prescriptionid);
    
  });

  //Form validation
  const form = document.querySelector('form');
  const submitBtn = form.querySelector('.btn-primary');
  const formFields = form.querySelectorAll('input, textarea');
    
  submitBtn.disabled = true;
    
  function validateForm() {
        let isValid = true;
        
        formFields.forEach(field => {
            if (field.type === 'checkbox') return;
            
            if (!field.value.trim()) {
                isValid = false;
            }
        });
        
        submitBtn.disabled = !isValid;
    }
    
    formFields.forEach(field => {
        field.addEventListener('input', validateForm);
    });
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (submitBtn.disabled) return;
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';
        

        const name = document.getElementById('name').value;
        const brand = document.getElementById('brand').value;
        const quantity = document.getElementById('quantity').value;
        const dosage = document.getElementById('dosage').value;
        const frequency = document.getElementById('frequency').value;
        const substitutions = document.getElementById('substitutions').checked ? '1' : '0';
        const description = document.getElementById('description').value;
        

        fetch(`http://localhost:8080/prescriptionitem_manager.php?action=addPrescriptionItem&prescriptionid=${encodeURIComponent(prescriptionid)}&name=${encodeURIComponent(name)}&brand=${encodeURIComponent(brand)}&quantity=${encodeURIComponent(quantity)}&dosage=${encodeURIComponent(dosage)}&frequency=${encodeURIComponent(frequency)}&substitutions=${encodeURIComponent(substitutions)}&description=${encodeURIComponent(description)}`)
            .then(response => response.json())
            .then(data => {
                form.reset();

                alert('Prescription item added successfully!');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the form');
            })
            .finally(() => {
                submitBtn.textContent = 'Add';
            });
    });
    
    validateForm();
});

document.getElementById("exit").addEventListener("click", () => {
  const popup = document.getElementsByClassName("view-wrapper");
  popup[0].style.visibility = "hidden";

  const popup2 = document.getElementsByClassName("container");
  popup2[0].style.visibility = "hidden";

  const form = document.querySelector('form');
  form.reset();
})

//PHP Functions
function phpDisplayAll(prescriptionid){
  fetch("http://localhost:8080/prescriptionitem_manager.php?action=getPrescriptionItems&prescriptionid=" + prescriptionid + "&sort=" + sortVal)
  .then(response => response.json())
  .then(data => {
      generateSearchResults(data);
  }).catch(error => console.error(error));
}

function searchItem(prescriptionid, prescriptionname){
  fetch("http://localhost:8080/prescriptionitem_manager.php?action=getPrescriptionItemsByName&prescriptionid=" + prescriptionid + "&prescriptionname=" + prescriptionname + "&sort=" + sortVal)
  .then(response => response.json())
  .then(data => {
      generateSearchResults(data);
  }).catch(error => console.error(error));
}

//Update Table
function generateSearchResults(pitems){
  const parent = document.getElementById("searchresults-container");

  while (parent.children.length > 1) {
    parent.removeChild(parent.lastElementChild); //clear the list
  }

  pitems.forEach(pitem => {

     const container = document.createElement("div");
     container.className = "card";
     if(pitem.quantity == 0){
     	container.style.backgroundColor = "#F9E1E1";
     }

     const medicinename = document.createElement("span");
     medicinename.textContent = pitem.name;

     const brand = document.createElement("span");
     brand.textContent = pitem.brand;

     const quantity = document.createElement("span");
     quantity.textContent = pitem.quantity;

     const dosage = document.createElement("span");
     dosage.textContent = pitem.dosage;

     const manageButton = document.createElement("button");
     manageButton.textContent = "View";
     manageButton.setAttribute("id", "manageButton");

     manageButton.addEventListener("click", function(event) {
        document.getElementById('name-value').textContent = pitem.name;
        document.getElementById('brand-value').textContent = pitem.brand;
        document.getElementById('qty-value').textContent = pitem.quantity;
        document.getElementById('dosage-value').textContent = pitem.dosage;
        document.getElementById('frequency-value').textContent = pitem.frequency + " time(s) a day";
        document.getElementById('substitutions-value').textContent = (pitem.substitutions == 1) ? "Allowed" : "Not Allowed";
        document.getElementById('description-value').textContent = pitem.description;

        const popup = document.getElementsByClassName("view-wrapper");
	    let rect = event.target.getBoundingClientRect();


	    popup[0].style.left = (rect.left * .7) + window.scrollX + "px";
	    popup[0].style.top = (rect.bottom * .9) + window.scrollY + "px";

	    popup[0].style.visibility = "visible";
     });

     container.appendChild(medicinename);
     container.appendChild(brand);
     container.appendChild(quantity);
     container.appendChild(dosage);
	 container.appendChild(manageButton);

     parent.appendChild(container);

  });
}

//Search Bar
const input = document.querySelector("input[name='search']");
input.addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
      let val = input.value.trim();
      if(val.length == 0){
        phpDisplayAll(prescriptionid);
      }else{        
        searchItem(prescriptionid, val);
      }
    }
});