// Central DayTech: botões "Copiar" (data-copiar="id do elemento com o texto").
document.addEventListener('click', async (evento) => {
  const botao = evento.target.closest('[data-copiar]');
  if (!botao) return;
  const alvo = document.getElementById(botao.dataset.copiar);
  if (!alvo) return;
  const original = botao.textContent;
  try {
    await navigator.clipboard.writeText(alvo.textContent.trim());
    botao.textContent = 'Copiado';
  } catch (e) {
    const faixa = document.createRange();
    faixa.selectNodeContents(alvo);
    const selecao = window.getSelection();
    selecao.removeAllRanges();
    selecao.addRange(faixa);
    botao.textContent = 'Selecionado: Ctrl+C';
  }
  setTimeout(() => { botao.textContent = original; }, 2000);
});
