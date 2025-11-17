document.addEventListener('DOMContentLoaded', () => {
  displayCart();

  const clearBtn = document.getElementById('clear-cart');
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      localStorage.removeItem('cart');
      displayCart();
    });
  }
});

function displayCart() {
  const cartItemsContainer = document.getElementById('cart-items');
  const cartTotalElement = document.getElementById('cart-total');
  const emptyMessage = document.getElementById('empty-cart-message');
  const cartContent = document.getElementById('cart-content');

  let cart = JSON.parse(localStorage.getItem('cart')) || [];

  // Verificăm dacă coșul e gol
  if (cart.length === 0) {
    emptyMessage.style.display = 'block';
    cartContent.style.display = 'none';
    return;
  } else {
    emptyMessage.style.display = 'none';
    cartContent.style.display = 'block';
  }

  cartItemsContainer.innerHTML = '';
  let total = 0;

  cart.forEach((product, index) => {
    // Asigurăm că avem cantitate (minim 1)
    const quantity = product.quantity || 1;
    const itemTotal = product.price * quantity;
    total += itemTotal;

    const itemDiv = document.createElement('div');
    itemDiv.classList.add('cart-item');

    itemDiv.innerHTML = `
      <img src="${product.image}" alt="${product.title}">
      
      <div class="item-details">
        <h3>${product.title}</h3>
        <p class="item-price">${product.price} ETH / buc</p>
        
        <div class="quantity-controls">
            <button class="qty-btn" onclick="changeQuantity(${index}, -1)">-</button>
            <span class="qty-number">${quantity}</span>
            <button class="qty-btn" onclick="changeQuantity(${index}, 1)">+</button>
        </div>

        <p style="font-size: 14px; color: #bbb; margin-top: 8px;">Subtotal: ${itemTotal.toFixed(2)} ETH</p>
      </div>

      <button class="remove-btn" onclick="removeFromCart(${index})">Șterge</button>
    `;

    cartItemsContainer.appendChild(itemDiv);
  });

  cartTotalElement.innerText = total.toFixed(2) + " ETH";
}

// Funcție nouă pentru modificarea cantității (+ sau -)
function changeQuantity(index, change) {
  let cart = JSON.parse(localStorage.getItem('cart')) || [];

  // Verificăm dacă produsul există
  if (cart[index]) {
    // Actualizăm cantitatea
    // (cart[index].quantity || 1) asigură că nu dă eroare la produse vechi
    let newQuantity = (cart[index].quantity || 1) + change;

    // Nu lăsăm cantitatea să scadă sub 1. 
    // (Dacă utilizatorul vrea 0, trebuie să apese butonul "Șterge")
    if (newQuantity < 1) {
      newQuantity = 1;
    }

    cart[index].quantity = newQuantity;
    
    // Salvăm și actualizăm pagina
    localStorage.setItem('cart', JSON.stringify(cart));
    displayCart();
  }
}

function removeFromCart(index) {
  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  cart.splice(index, 1);
  localStorage.setItem('cart', JSON.stringify(cart));
  displayCart();
}