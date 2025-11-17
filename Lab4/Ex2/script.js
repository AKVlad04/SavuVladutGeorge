document.addEventListener("DOMContentLoaded", function() {
    
    const divDetalii = document.getElementById('detalii');
    const btnDetalii = document.getElementById('btnDetalii');
    const spanData = document.getElementById('dataProdus');

    divDetalii.classList.add('ascuns');

    const dataCurenta = new Date();

    const luni = [
        "Ianuarie", "Februarie", "Martie", "Aprilie", "Mai", "Iunie",
        "Iulie", "August", "Septembrie", "Octombrie", "Noiembrie", "Decembrie"
    ];

    const ziua = dataCurenta.getDate();
    const lunaText = luni[dataCurenta.getMonth()];
    const anul = dataCurenta.getFullYear();

    const textData = `${ziua} ${lunaText} ${anul}`;
    
    spanData.textContent = textData;

    btnDetalii.addEventListener('click', function() {
        divDetalii.classList.toggle('ascuns');

        if (divDetalii.classList.contains('ascuns')) {
            btnDetalii.textContent = "Afișează detalii";
        } else {
            btnDetalii.textContent = "Ascunde detalii";
        }
    });

});