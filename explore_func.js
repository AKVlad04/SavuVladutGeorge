document.addEventListener('DOMContentLoaded', () => {
  const buyButtons = document.querySelectorAll('.buy-btn');

  buyButtons.forEach(button => {
    button.addEventListener('click', (event) => {
      // Găsim elementul părinte (.nft-card)
      const card = event.target.closest('.nft-card');
      
      // Extragem datele
      const title = card.querySelector('h3').innerText;
      const priceText = card.querySelector('p').innerText; 
      const imageSrc = card.querySelector('img').src;

      // Curățăm prețul pentru a păstra doar numărul (ex: "Preț: 0.5 ETH" -> 0.5)
      const price = parseFloat(priceText.replace('Preț:', '').replace('ETH', '').trim());

      // Creăm obiectul produs, inițial cu cantitatea 1
      const product = {
        title: title,
        price: price,
        image: imageSrc,
        quantity: 1
      };

      // Apelăm funcția de adăugare
      addToCart(product);
    });
  });
});

function addToCart(product) {
  // Luăm coșul existent sau creăm unul gol
  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  
  // Căutăm dacă produsul există deja în coș (după titlu)
  const existingProductIndex = cart.findIndex(item => item.title === product.title);

  if (existingProductIndex > -1) {
    // CAZ 1: Produsul există deja -> Creștem cantitatea
    // Folosim (cart[...].quantity || 1) pentru a repara produsele vechi care nu aveau cantitate setată
    cart[existingProductIndex].quantity = (cart[existingProductIndex].quantity || 1) + 1;
    alert(`Ai mai adăugat o bucată de ${product.title}!`);
  } else {
    // CAZ 2: Produs nou -> Îl adăugăm în listă
    cart.push(product);
    alert(`${product.title} a fost adăugat în coș!`);
  }
  
  // Salvăm înapoi în browser
  localStorage.setItem('cart', JSON.stringify(cart));
}