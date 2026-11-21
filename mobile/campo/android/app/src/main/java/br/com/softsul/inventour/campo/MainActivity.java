package br.com.softsul.inventour.campo;

import android.graphics.Color;
import android.os.Bundle;
import android.view.View;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;
import androidx.activity.OnBackPressedCallback;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.BridgeActivity;

/**
 * InvenTour Campo: casca Android da coleta (a tela vem dentro do app).
 * Softsul Software
 *
 * Com alvo Android 15+ o sistema desenha o app atrás da barra de status e da
 * barra de navegação: aqui a página fica entre as barras e acima do teclado
 * (molde do EventCity). O voltar do Android fecha a janela aberta na página
 * (a ficha do item); sem nada aberto, a página pergunta se quer sair (para
 * não sair sem querer), e só o "Sim, sair" fecha o app, pela ponte
 * InvenTourNativo.sair(). O aviso de atualização obrigatória não fecha.
 */
public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        telaEntreAsBarras();
        if (getBridge() != null) {
            getBridge().getWebView().addJavascriptInterface(new Object() {
                @JavascriptInterface
                public void sair() {
                    runOnUiThread(() -> finish());
                }
            }, "InvenTourNativo");
        }
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                WebView web = getBridge() != null ? getBridge().getWebView() : null;
                if (web == null) {
                    finish();
                    return;
                }
                web.evaluateJavascript(
                    "(function(){var d=[].slice.call(document.querySelectorAll('dialog[open]')).pop();"
                        + "if(d){if(d.hasAttribute('data-fixo')){return 'fixo';}d.close();return 'fechou';}"
                        + "if(window.inventourSair){window.inventourSair();return 'perguntou';}return 'nada';})()",
                    r -> {
                        // Só sai direto se a página não respondeu (ainda carregando).
                        if (r == null || r.contains("nada")) {
                            finish();
                        }
                    });
            }
        });
    }

    private void telaEntreAsBarras() {
        if (getBridge() == null) {
            return;
        }
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        View pai = (View) getBridge().getWebView().getParent();
        // Fundo branco atrás das barras, com ícones escuros: combina com o topo do app.
        pai.setBackgroundColor(Color.WHITE);
        getWindow().getDecorView().setBackgroundColor(Color.WHITE);
        WindowInsetsControllerCompat c = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        c.setAppearanceLightStatusBars(true);
        c.setAppearanceLightNavigationBars(true);
        ViewCompat.setOnApplyWindowInsetsListener(pai, (v, insets) -> {
            Insets barras = insets.getInsets(WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.displayCutout());
            Insets teclado = insets.getInsets(WindowInsetsCompat.Type.ime());
            v.setPadding(barras.left, barras.top, barras.right, Math.max(barras.bottom, teclado.bottom));
            return WindowInsetsCompat.CONSUMED;
        });
        ViewCompat.requestApplyInsets(pai);
    }
}
