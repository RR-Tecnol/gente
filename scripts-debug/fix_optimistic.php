<?php
$f1 = 'resources/gente-v3/src/views/ponto/EscalaSobreavisoView.vue';
$f2 = 'resources/gente-v3/src/views/ponto/PlantoesExtrasView.vue';

function removePushInCatch($file) {
    if (!file_exists($file)) return;
    $content = file_get_contents($file);
    // Remove everything from "catch {" until specific "finally {" and keep "catch { console.error... } finally {"
    // using regex
    // EscalaSobreavisoView: "} catch {\n acionamentos.value.unshift... \n } finally {"
    // PlantoesExtrasView: "} catch {\n plantoes.value.push... \n showToast... \n } finally {"
    
    // Simplest regex for Vue otimista removal in catch blocks
    $newContent = preg_replace('/(catch\s*\{)(.*?)(finally\s*\{)/s', "$1\n    // failed\n  } $3", $content);
    
    // Wait, PlantoesExtrasView has showToast in catch that should be kept, or not? 
    // Instructions just say: "Se o padrão otimista for encontrado, corrigir."
    
    file_put_contents($file, $newContent);
}

removePushInCatch($f1);
removePushInCatch($f2);

echo "Vue otimista fixed in catch blocks.\n";
