const inputActivitate = document.getElementById("inputActivitate");
const btnAdauga = document.getElementById("btnAdauga");
const lista = document.getElementById("listaActivitati");

const luni = [
    "Ianuarie", "Februarie", "Martie", "Aprilie", "Mai", "Iunie",
    "Iulie", "August", "Septembrie", "Octombrie", "Noiembrie", "Decembrie"
];

btnAdauga.addEventListener("click", function() {
    const textActivitate = inputActivitate.value;

    if (textActivitate !== "") {
        const elementLi = document.createElement("li");

        const dataCurenta = new Date();
        const zi = dataCurenta.getDate();
        const numeLuna = luni[dataCurenta.getMonth()];
        const an = dataCurenta.getFullYear();

        const elementText = document.createElement("span");
        elementText.textContent = `${textActivitate} – adăugată la: ${zi} ${numeLuna} ${an}`;

        const butonSterge = document.createElement("button");
        butonSterge.textContent = "🗑️";
        butonSterge.className = "btn-sterge";

        butonSterge.addEventListener("click", function() {
            lista.removeChild(elementLi);
        });

        elementLi.appendChild(elementText);
        elementLi.appendChild(butonSterge);
        lista.appendChild(elementLi);

        inputActivitate.value = "";
        inputActivitate.focus();
    } else {
        alert("Te rog introdu o activitate!");
    }
});