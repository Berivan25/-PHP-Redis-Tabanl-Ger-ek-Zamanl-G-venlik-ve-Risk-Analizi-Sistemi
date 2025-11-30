function sendBehaviorEvent(eventType, eventData = {}) {
  fetch('/app2/user_behavior.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      event_type: eventType,
      event_data: eventData
    })
  }).then(res => res.json())
    .then(data => {
      if(data.status !== 'success') {
        console.error('Davranış kaydı başarısız:', data.message);
      }
    }).catch(err => {
      console.error('Fetch hatası:', err);
    });
}


window.addEventListener('load', () => {
  sendBehaviorEvent('page_view', {
    page: window.location.pathname,
    title: document.title
  });
});


let inactivityTimer;
function resetInactivityTimer() {
  clearTimeout(inactivityTimer);
  inactivityTimer = setTimeout(() => {
    sendBehaviorEvent('mouse_inactive', { duration: 5 });
  }, 5000);
}
window.addEventListener('mousemove', resetInactivityTimer);
resetInactivityTimer();


document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', () => {
    sendBehaviorEvent('form_submission', { form_action: form.action });
  });
});



// -----------------------
// 🧠 Davranışsal İzleme
// -----------------------
let mouseMoveCount = 0;
let totalMouseDistance = 0;
let keyStrokeCount = 0;
let lastX = null;
let lastY = null;
const sessionStartTime = Date.now();

// Mouse hareketlerini say ve mesafeyi ölç
document.addEventListener('mousemove', function(e) {
  mouseMoveCount++;
  if (lastX !== null && lastY !== null) {
    const dx = e.clientX - lastX;
    const dy = e.clientY - lastY;
    totalMouseDistance += Math.sqrt(dx * dx + dy * dy);
  }
  lastX = e.clientX;
  lastY = e.clientY;
});

// Klavye tuşlamalarını say
document.addEventListener('keydown', function() {
  keyStrokeCount++;
});

// Sayfa kapanırken verileri gönder
window.addEventListener('beforeunload', function() {
  const duration = Date.now() - sessionStartTime;

  const behaviorData = {
    mouseMoves: mouseMoveCount,
    totalDistance: Math.round(totalMouseDistance),
    keyStrokes: keyStrokeCount,
    duration: duration
  };

  // Beacon ile gönder (senkron çalışır)
  navigator.sendBeacon(
    '/app2/behavior_tracker.php',
    JSON.stringify(behaviorData)
  );
});
