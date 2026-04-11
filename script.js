const products = [
  {
    id: "encapsulado-premium",
    name: "Encapsulado Premium",
    description: "Globo transparente con mini peluche, luces y mensaje personalizado.",
    price: 85,
    image: "./assets/encapsulado.svg"
  },
  {
    id: "bandeja-desayuno-deluxe",
    name: "Bandeja de Desayuno Deluxe",
    description: "Incluye snacks, bebida, globo temático y dedicatoria.",
    price: 120,
    image: "./assets/bandeja.svg"
  },
  {
    id: "burbuja-pintada",
    name: "Burbuja Pintada",
    description: "Diseño artístico a mano sobre globo burbuja para regalo especial.",
    price: 95,
    image: "./assets/burbuja.svg"
  },
  {
    id: "bouquet-festivo",
    name: "Bouquet Festivo",
    description: "Arreglo de globos metalizados y latex con combinación a elección.",
    price: 75,
    image: "./assets/bouquet.svg"
  },
  {
    id: "arco-decorativo",
    name: "Arco Decorativo",
    description: "Instalación de arco orgánico para ingreso o mesa principal.",
    price: 260,
    image: "./assets/evento.svg"
  },
  {
    id: "combo-sorpresa",
    name: "Combo Sorpresa",
    description: "Encapsulado + bouquet + mini bandeja para sorprender en grande.",
    price: 189,
    image: "./assets/hero-balloons.svg"
  }
];

const cart = new Map();
const catalogGrid = document.getElementById("catalog-grid");
const cartItems = document.getElementById("cart-items");
const cartTotal = document.getElementById("cart-total");
const cartCount = document.getElementById("cart-count");
const cartToggle = document.getElementById("cart-toggle");
const cartBody = document.querySelector(".cart-body");
const sendOrderBtn = document.getElementById("send-order");
const contactForm = document.getElementById("contact-form");

const money = new Intl.NumberFormat("es-PE", {
  style: "currency",
  currency: "PEN",
  minimumFractionDigits: 2
});

function renderCatalog() {
  catalogGrid.innerHTML = products
    .map(
      (product) => `
      <article class="product-card">
        <img class="product-media" src="${product.image}" alt="${product.name}">
        <div class="product-body">
          <h3 class="product-title">${product.name}</h3>
          <p class="product-desc">${product.description}</p>
          <div class="product-row">
            <span class="price">${money.format(product.price)}</span>
            <button class="btn btn-sm add-btn" data-id="${product.id}" type="button">Agregar</button>
          </div>
        </div>
      </article>
    `
    )
    .join("");

  document.querySelectorAll(".add-btn").forEach((button) => {
    button.addEventListener("click", () => {
      const id = button.dataset.id;
      addToCart(id);
    });
  });
}

function addToCart(id) {
  const currentQty = cart.get(id) || 0;
  cart.set(id, currentQty + 1);
  renderCart();
}

function removeFromCart(id) {
  const currentQty = cart.get(id) || 0;
  if (currentQty <= 1) {
    cart.delete(id);
  } else {
    cart.set(id, currentQty - 1);
  }
  renderCart();
}

function getCartSummary() {
  let total = 0;
  let count = 0;
  const lines = [];

  cart.forEach((qty, id) => {
    const product = products.find((p) => p.id === id);
    if (!product) {
      return;
    }
    const subtotal = product.price * qty;
    total += subtotal;
    count += qty;
    lines.push(`${qty}x ${product.name} - ${money.format(subtotal)}`);
  });

  return { total, count, lines };
}

function renderCart() {
  const { total, count } = getCartSummary();
  cartCount.textContent = String(count);
  cartTotal.textContent = money.format(total);

  if (count === 0) {
    cartItems.innerHTML = `<li class="cart-item"><span>Tu carrito está vacío</span></li>`;
    return;
  }

  cartItems.innerHTML = Array.from(cart.entries())
    .map(([id, qty]) => {
      const product = products.find((p) => p.id === id);
      if (!product) {
        return "";
      }
      return `
      <li class="cart-item">
        <div>
          <strong>${product.name}</strong><br>
          <small>${qty} x ${money.format(product.price)}</small>
        </div>
        <button class="btn btn-sm remove-btn" data-id="${id}" type="button">Quitar</button>
      </li>
      `;
    })
    .join("");

  document.querySelectorAll(".remove-btn").forEach((button) => {
    button.addEventListener("click", () => {
      const id = button.dataset.id;
      removeFromCart(id);
    });
  });
}

function sendOrder() {
  const { lines, total, count } = getCartSummary();
  if (count === 0) {
    alert("Agrega al menos un producto a tu pedido.");
    return;
  }

  const text = [
    "Hola ArtiGlow, quiero realizar este pedido:",
    "",
    ...lines,
    "",
    `Total estimado: ${money.format(total)}`,
    "¿Me ayudan con disponibilidad y entrega?"
  ].join("\n");

  const url = `https://wa.me/51999888777?text=${encodeURIComponent(text)}`;
  window.open(url, "_blank");
}

function submitContactForm(event) {
  event.preventDefault();
  const name = document.getElementById("name").value.trim();
  const phone = document.getElementById("phone").value.trim();
  const eventType = document.getElementById("eventType").value.trim();
  const details = document.getElementById("details").value.trim();

  const text = [
    "Hola ArtiGlow, quiero cotizar un servicio:",
    `Nombre: ${name}`,
    `Celular: ${phone}`,
    `Evento: ${eventType}`,
    `Detalle: ${details || "Sin detalle adicional"}`
  ].join("\n");

  const url = `https://wa.me/51999888777?text=${encodeURIComponent(text)}`;
  window.open(url, "_blank");
  contactForm.reset();
}

cartToggle.addEventListener("click", () => {
  cartBody.classList.toggle("open");
});

sendOrderBtn.addEventListener("click", sendOrder);
contactForm.addEventListener("submit", submitContactForm);

renderCatalog();
renderCart();
