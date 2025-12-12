document.addEventListener("DOMContentLoaded", () => {
  phpDisplayAll(41); //Simulate Doctor Id. 41
});

//NODE TO
function phpDisplayAll(doctorid){
  fetch("http://localhost:3000/getpatients?id=" + doctorid)
  .then(response => response.json())
  .then(data => {
      generateSearchResults(data);
  }).catch(error => console.error(error));
}

function phpSearch(doctorid, patientName){
  fetch("http://localhost:8080/patient_manager.php?action=getPatientByName&doctorid=" + doctorid + "&patientname=" + patientName)
  .then(response => response.json())
  .then(data => {
      generateSearchResults(data);
  }).catch(error => console.error(error));
}

function generateSearchResults(patients){
  const parent = document.getElementById("searchresults-container");

  while (parent.children.length > 1) {
    parent.removeChild(parent.lastElementChild); //clear the list
  }

  patients.forEach(patient => {

     const container = document.createElement("div");
     container.className = "card";

     const nameWrapper = document.createElement("div");
     nameWrapper.style.display = "flex";
     nameWrapper.style.flexDirection = "column";

     const name = document.createElement("span");
     name.textContent = patient.firstname + " " + patient.lastname;

     const gender = document.createElement("span");
     gender.textContent = patient.gender;
     gender.style.fontSize = "13px"

     nameWrapper.appendChild(name);
     nameWrapper.appendChild(gender);

     const contactWrapper = document.createElement("div");
     contactWrapper.style.display = "flex";
     contactWrapper.style.flexDirection = "column";

     const email = document.createElement("span");
     email.textContent = patient.email;

     const contactnum = document.createElement("span");
     contactnum.textContent = "+" + patient.contactnum;
     contactnum.style.fontSize = "13px"

     contactWrapper.appendChild(email);
     contactWrapper.appendChild(contactnum);

     const lastactivity = document.createElement("span");
     lastactivity.textContent = patient.dateprescribed;

     const manageButton = document.createElement("button");
     manageButton.textContent = "Manage";
     manageButton.setAttribute("id", "manageButton");

     manageButton.addEventListener("click", () => {
      window.location.href = `prescription_manager.html?patientid=${patient.userid}&doctorid=41`;
     });

     container.appendChild(nameWrapper);
     container.appendChild(contactWrapper);
     container.appendChild(lastactivity);
     container.appendChild(manageButton);

     parent.appendChild(container);

  });
}

const input = document.querySelector("input[name='search']");
input.addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
      let val = input.value.trim();
      if(val.length == 0){
        phpDisplayAll(41);
      }else{        
        phpSearch(41, val);
      }
    }
});