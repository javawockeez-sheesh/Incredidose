const params = new URLSearchParams(window.location.search);
const doctorid = params.get("doctorid");
const patientid = params.get("patientid");


document.getElementById("exit").addEventListener("click", () => {
  const popup = document.getElementsByClassName("container");
  popup[0].style.visibility = "hidden";

  const form = document.querySelector('form');
  form.reset();
});
  
document.addEventListener("DOMContentLoaded", () => {
  phpDisplayAll(patientid);

  const addButton = document.querySelector("#add");
  addButton.addEventListener("click", () => {
     const popup = document.getElementsByClassName("container");
     let rect = event.target.getBoundingClientRect();

     popup[0].style.left = (rect.left * .7) + window.scrollX + "px";
     popup[0].style.top = (rect.bottom * .9) + window.scrollY + "px";

     popup[0].style.visibility = "visible";
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
        
        const value = document.getElementById('date').value;
        let parts = value.split("/");
        let date = parts[0];

        fetch(`http://localhost:8080/prescription_manager.php?action=addPrescription&validperiod=${(date)}&doctorid=${doctorid}&patientid=${patientid}`)
            .then(response => response.json())
            .then(data => {

              prescriptionid = data.id;
          
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
                      window.location.href = `prescriptionitem_manager.html?patientid&=${patientid}&prescriptionid=${prescriptionid}`;
                  })
                  .catch(error => {
                      console.error('Error:', error);
                      form.reset();
                      alert('An error occurred while submitting the form');
                  })
                  .finally(() => {
                      submitBtn.textContent = 'Add';
                  });

                  }).finally(() => {
                      submitBtn.textContent = 'Add';
                  });
    });
    
    validateForm();
});

function phpDisplayAll(patientid){
  fetch("http://localhost:8080/prescription_manager.php?action=getPrescriptions&patientid=" + patientid)
  .then(response => response.json())
  .then(data => {
      generateSearchResults(data);
  }).catch(error => console.error(error));
}

function generateSearchResults(prescriptions){
  const parent = document.getElementById("searchresults-container");

  while (parent.children.length > 1) {
    parent.removeChild(parent.lastElementChild); //clear the list
  }

  prescriptions.forEach(prescription => {
     const container = document.createElement("div");
     container.className = "card";
     container.style.display = "flex";
     container.style.flexDirection = "row"
     container.style.justifyContent = "space-between";

     const infoWrapper = document.createElement("div");
     infoWrapper.style.display = "flex";
     infoWrapper.style.flexDirection = "column"

     const date = document.createElement("span");
     date.textContent = `Issued on ${prescription.dateprescribed}`;
     date.style.fontWeight = "750";

     const email = document.createElement("span");
     email.textContent = prescription.email;
     email.style.fontSize = "13px";

     const contactnum = document.createElement("span");
     contactnum.textContent = "+" + prescription.contactnum;
     contactnum.style.fontSize = "13px";

     const manageButton = document.createElement("button");
     manageButton.textContent = "View";
     manageButton.setAttribute("id", "manageButton");

     manageButton.addEventListener("click", () => {
      window.location.href = `prescriptionitem_manager.html?patientid&=${prescription.patientid}&prescriptionid=${prescription.prescriptionid}`;
     });

     infoWrapper.appendChild(date);
     infoWrapper.appendChild(email);
     infoWrapper.appendChild(contactnum);

     container.appendChild(infoWrapper);
     container.appendChild(manageButton);

     parent.appendChild(container);

  });
}