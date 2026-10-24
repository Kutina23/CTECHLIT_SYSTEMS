// Nav bg on scroll
const nav = document.getElementById('nav');
window.addEventListener('scroll', ()=> nav.classList.toggle('scrolled', window.scrollY>40));

// Reveal on scroll
const io = new IntersectionObserver((entries)=>{
  entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target);} });
},{threshold:.15, rootMargin:"0px 0px -60px 0px"});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

// Stagger text animation on scroll
const staggerIO = new IntersectionObserver((entries)=>{
  entries.forEach(e=>{
    if(e.isIntersecting){
      e.target.classList.add('in');
      staggerIO.unobserve(e.target);
    }
  });
},{threshold:.1, rootMargin:"0px 0px -40px 0px"});
document.querySelectorAll('.stagger-text').forEach(el=>staggerIO.observe(el));

// Parallax scrolling
(function(){
  const parallaxElements = document.querySelectorAll('[data-parallax-speed]');
  let ticking = false;
  let lastScrollY = 0;
  
  function updateParallax(){
    const scrollY = window.scrollY;
    const scrollDelta = scrollY - lastScrollY;
    lastScrollY = scrollY;
    
    parallaxElements.forEach(el=>{
      const speed = parseFloat(el.dataset.parallaxSpeed) || 0.5;
      const rect = el.parentElement.getBoundingClientRect();
      const elementCenter = rect.top + rect.height / 2;
      const windowCenter = window.innerHeight / 2;
      const distance = elementCenter - windowCenter;
      const offset = distance * speed * 0.2;
      el.style.transform = `translateY(${offset}px)`;
    });
    ticking = false;
  }
  
  window.addEventListener('scroll', ()=>{
    if(!ticking){
      requestAnimationFrame(updateParallax);
      ticking = true;
    }
  }, {passive: true});
  
  // Initial update
  updateParallax();
  
  // Add parallax to all sections
  document.querySelectorAll('section').forEach(section=>{
    if(!section.querySelector('.parallax-bg') && !section.querySelector('.parallax-layer')){
      const layer = document.createElement('div');
      layer.className = 'parallax-layer layer-2';
      layer.setAttribute('data-parallax-speed', '0.15');
      section.insertBefore(layer, section.firstChild);
    }
  });
})();

// Magnetic Button Effect
(function(){
  const buttons = document.querySelectorAll('.btn');
  const strength = 0.3;
  const radius = 100;
  
  buttons.forEach(btn=>{
    btn.classList.add('magnetic');
    
    btn.addEventListener('mousemove', (e)=>{
      const rect = btn.getBoundingClientRect();
      const x = e.clientX - rect.left - rect.width / 2;
      const y = e.clientY - rect.top - rect.height / 2;
      const distance = Math.sqrt(x * x + y * y);
      
      if(distance < radius){
        const power = (radius - distance) / radius;
        const moveX = x * strength * power;
        const moveY = y * strength * power;
        btn.style.transform = `translate(${moveX}px, ${moveY}px)`;
      }
    });
    
    btn.addEventListener('mouseleave', ()=>{
      btn.style.transform = 'translate(0, 0)';
    });
  });
})();

// 3D scroll-linked particle network background
(function(){
  try{
    const canvas = document.getElementById('bg-canvas');
    const renderer = new THREE.WebGLRenderer({canvas, alpha:true, antialias:true});
    renderer.setPixelRatio(Math.min(window.devicePixelRatio,2));
    renderer.setSize(window.innerWidth, window.innerHeight);
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(60, window.innerWidth/window.innerHeight, 0.1, 100);
    camera.position.z = 9;

    const COUNT = 130;
    const positions = new Float32Array(COUNT*3);
    for(let i=0;i<COUNT;i++){
      positions[i*3] = (Math.random()-0.5)*18;
      positions[i*3+1] = (Math.random()-0.5)*18;
      positions[i*3+2] = (Math.random()-0.5)*10;
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(positions,3));
    const mat = new THREE.PointsMaterial({color:0x7C4DFF, size:0.06, transparent:true, opacity:.85});
    const points = new THREE.Points(geo, mat);
    scene.add(points);

    // connecting lines between near points (computed once)
    const linePositions = [];
    for(let i=0;i<COUNT;i++){
      for(let j=i+1;j<COUNT;j++){
        const dx=positions[i*3]-positions[j*3], dy=positions[i*3+1]-positions[j*3+1], dz=positions[i*3+2]-positions[j*3+2];
        const d = Math.sqrt(dx*dx+dy*dy+dz*dz);
        if(d<2.6){
          linePositions.push(positions[i*3],positions[i*3+1],positions[i*3+2], positions[j*3],positions[j*3+1],positions[j*3+2]);
        }
      }
    }
    const lineGeo = new THREE.BufferGeometry();
    lineGeo.setAttribute('position', new THREE.BufferAttribute(new Float32Array(linePositions),3));
    const lineMat = new THREE.LineBasicMaterial({color:0x2FD9EE, transparent:true, opacity:.22});
    const lines = new THREE.LineSegments(lineGeo, lineMat);
    scene.add(lines);

    const group = new THREE.Group(); group.add(points); group.add(lines);
    scene.remove(points); scene.remove(lines); scene.add(group);

    let mouseX=0, mouseY=0, scrollT=0;
    window.addEventListener('mousemove', e=>{
      mouseX = (e.clientX/window.innerWidth - .5);
      mouseY = (e.clientY/window.innerHeight - .5);
    });
    function updateScroll(){
      const h = document.documentElement.scrollHeight - window.innerHeight;
      scrollT = h>0 ? window.scrollY/h : 0;
    }
    window.addEventListener('scroll', updateScroll);
    window.addEventListener('resize', ()=>{
      camera.aspect = window.innerWidth/window.innerHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(window.innerWidth, window.innerHeight);
    });
    updateScroll();

    function animate(){
      requestAnimationFrame(animate);
      group.rotation.y = scrollT * Math.PI * 1.4 + mouseX*0.3;
      group.rotation.x = scrollT * Math.PI * 0.5 + mouseY*0.2;
      group.position.z = -scrollT*4;
      camera.position.x += (mouseX*0.6 - camera.position.x)*0.03;
      camera.position.y += (-mouseY*0.6 - camera.position.y)*0.03;
      camera.lookAt(scene.position);
      renderer.render(scene, camera);
    }
    animate();
  }catch(e){ console.warn('3D background unavailable', e); }
})();

// FAQ accordion (one open at a time; the question is a real button for keyboard/screen readers)
document.querySelectorAll('.faq-item').forEach(item=>{
  const trigger = item.querySelector('.faq-question');
  if(!trigger) return;
  trigger.addEventListener('click', ()=>{
    const isActive = item.classList.contains('active');
    document.querySelectorAll('.faq-item').forEach(i=>{
      i.classList.remove('active');
      const q = i.querySelector('.faq-question');
      if(q) q.setAttribute('aria-expanded', 'false');
    });
    if(!isActive){
      item.classList.add('active');
      trigger.setAttribute('aria-expanded', 'true');
    }
  });
});

// Testimonials carousel
(function(){
  const carousels = document.querySelectorAll('.testimonial-carousel');
  carousels.forEach(carousel=>{
    const track = carousel.querySelector('.testimonial-track');
    const cards = Array.from(track.querySelectorAll('.testimonial-card'));
    const prevBtn = carousel.querySelector('.carousel-prev');
    const nextBtn = carousel.querySelector('.carousel-next');
    const dotsContainer = carousel.querySelector('.carousel-dots');
    let currentIndex = 0;
    let autoInterval = null;
    let isDragging = false;
    let startPos = 0;
    let currentTranslate = 0;
    let prevTranslate = 0;

    function getVisibleCount(){
      const w = window.innerWidth;
      if(w <= 600) return 1;
      if(w <= 860) return 2;
      return 3;
    }

    function getMaxIndex(){
      const visible = getVisibleCount();
      return Math.max(0, cards.length - visible);
    }

    function getCardWidth(){
      return cards[0].offsetWidth + parseFloat(getComputedStyle(track).gap || 22);
    }

    function updateCarousel(animate){
      const maxIndex = getMaxIndex();
      currentIndex = Math.max(0, Math.min(currentIndex, maxIndex));
      const offset = currentIndex * getCardWidth();
      track.style.transition = animate !== false ? 'transform .5s cubic-bezier(.4,.2,.2,1)' : 'none';
      track.style.transform = `translateX(-${offset}px)`;
      prevTranslate = offset;
      updateDots();
      updateButtons();
    }

    function updateDots(){
      const maxIndex = getMaxIndex();
      const dots = dotsContainer.querySelectorAll('.carousel-dot');
      dots.forEach((dot, i)=>{
        dot.classList.toggle('active', i <= maxIndex && i === currentIndex);
      });
    }

    function updateButtons(){
      const maxIndex = getMaxIndex();
      if(prevBtn) prevBtn.style.opacity = currentIndex <= 0 ? '.4' : '1';
      if(nextBtn) nextBtn.style.opacity = currentIndex >= maxIndex ? '.4' : '1';
    }

    function goNext(){
      const maxIndex = getMaxIndex();
      if(currentIndex < maxIndex){
        currentIndex++;
        updateCarousel();
        resetAuto();
      }
    }

    function goPrev(){
      if(currentIndex > 0){
        currentIndex--;
        updateCarousel();
        resetAuto();
      }
    }

    function startAuto(){
      stopAuto();
      autoInterval = setInterval(goNext, 5000);
    }

    function stopAuto(){
      if(autoInterval) clearInterval(autoInterval);
    }

    function resetAuto(){
      stopAuto();
      startAuto();
    }

    // Build dots
    const maxIndex = getMaxIndex();
    for(let i=0; i<=maxIndex; i++){
      const dot = document.createElement('button');
      dot.className = 'carousel-dot' + (i===0?' active':'');
      dot.setAttribute('aria-label', `Go to testimonial ${i+1}`);
      dot.addEventListener('click', ()=>{
        currentIndex = i;
        updateCarousel();
        resetAuto();
      });
      dotsContainer.appendChild(dot);
    }

    // Event listeners
    if(prevBtn) prevBtn.addEventListener('click', goPrev);
    if(nextBtn) nextBtn.addEventListener('click', goNext);

    // Touch / drag support
    track.addEventListener('touchstart', (e)=>{
      isDragging = true;
      startPos = e.touches[0].clientX;
      track.style.transition = 'none';
      stopAuto();
    }, {passive: true});

    track.addEventListener('touchmove', (e)=>{
      if(!isDragging) return;
      const currentPos = e.touches[0].clientX;
      const diff = currentPos - startPos;
      currentTranslate = prevTranslate + diff;
      track.style.transform = `translateX(-${currentTranslate}px)`;
    }, {passive: true});

    track.addEventListener('touchend', (e)=>{
      if(!isDragging) return;
      isDragging = false;
      const movedBy = currentTranslate - prevTranslate;
      if(movedBy < -50) goNext();
      else if(movedBy > 50) goPrev();
      updateCarousel();
      startAuto();
    });

    // Mouse drag support
    track.addEventListener('mousedown', (e)=>{
      isDragging = true;
      startPos = e.clientX;
      track.style.transition = 'none';
      track.style.cursor = 'grabbing';
      stopAuto();
    });

    window.addEventListener('mousemove', (e)=>{
      if(!isDragging) return;
      const currentPos = e.clientX;
      const diff = currentPos - startPos;
      currentTranslate = prevTranslate + diff;
      track.style.transform = `translateX(-${currentTranslate}px)`;
    });

    window.addEventListener('mouseup', (e)=>{
      if(!isDragging) return;
      isDragging = false;
      track.style.cursor = '';
      const movedBy = currentTranslate - prevTranslate;
      if(movedBy < -50) goNext();
      else if(movedBy > 50) goPrev();
      updateCarousel();
      startAuto();
    });

    // Pause on hover
    carousel.addEventListener('mouseenter', stopAuto);
    carousel.addEventListener('mouseleave', startAuto);

    // Resize handler
    let resizeTimeout;
    window.addEventListener('resize', ()=>{
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(()=>{
        currentIndex = Math.min(currentIndex, getMaxIndex());
        updateCarousel(false);
      }, 150);
    });

    // Init
    updateCarousel(false);
    startAuto();
  });
})();


// ---------- Homepage refresh: section highlighting, filters, tabs, back-to-top ----------
(function(){
  // Highlight the nav link for the section in view.
  const spyLinks = Array.from(document.querySelectorAll('.navlinks a[data-spy]'));
  const targets = spyLinks.map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);
  if ('IntersectionObserver' in window && targets.length) {
    const spy = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        spyLinks.forEach(a => {
          const on = a.getAttribute('href') === '#' + entry.target.id;
          a.classList.toggle('active', on);
          if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
        });
      });
    }, {rootMargin: '-45% 0px -50% 0px'});
    targets.forEach(t => spy.observe(t));
  }

  // Close the mobile menu after choosing a section.
  const menu = document.getElementById('primaryNav');
  const burger = document.querySelector('#nav .hamburger');
  if (menu && burger) {
    menu.addEventListener('click', e => {
      if (e.target.closest('a[href^="#"]') && menu.classList.contains('open')) burger.click();
    });
  }

  // Service category filter.
  const chips = document.querySelectorAll('.filter-chips .chip');
  chips.forEach(chip => chip.addEventListener('click', () => {
    const filter = chip.dataset.filter;
    chips.forEach(c => c.setAttribute('aria-pressed', String(c === chip)));
    document.querySelectorAll('#serviceGrid .svc-card').forEach(card => {
      const show = filter === 'all' || card.dataset.category === filter;
      card.classList.toggle('is-hidden', !show);
      if (show) card.classList.add('in');
    });
  }));

  // Pricing tabs (arrow keys move between tabs).
  const tabs = Array.from(document.querySelectorAll('.seg-tabs [role="tab"]'));
  if (tabs.length) {
    document.body.classList.add('has-price-tabs');
    const select = tab => {
      tabs.forEach(t => {
        const on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        const panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) {
          panel.hidden = !on;
          if (on) panel.querySelectorAll('.reveal').forEach(el => el.classList.add('in'));
        }
      });
    };
    tabs.forEach((tab, i) => {
      tab.addEventListener('click', () => select(tab));
      tab.addEventListener('keydown', e => {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        const next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
        select(next);
        next.focus();
      });
    });
    select(tabs[0]);
  }

  // Back-to-top button.
  const toTop = document.querySelector('.to-top');
  if (toTop) {
    const update = () => toTop.classList.toggle('show', window.scrollY > 900);
    window.addEventListener('scroll', update, {passive: true});
    update();
  }
})();
