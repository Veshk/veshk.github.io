// Official IFrame API: https://docs.kinescope.com/player-docs/embedding/iframe-api/
let factoryPromise, sequence=0;

function loadFactory() {
  if(window.Kinescope?.IframePlayer)return Promise.resolve(window.Kinescope.IframePlayer);
  if(factoryPromise)return factoryPromise;
  factoryPromise=new Promise((resolve,reject)=>{
    const script=document.createElement('script');
    const previous=window.onKinescopeIframeAPIReady;
    let settled=false;
    const finish=(error,factory)=>{
      if(settled)return;
      settled=true;clearTimeout(timer);
      if(window.onKinescopeIframeAPIReady===ready)window.onKinescopeIframeAPIReady=previous;
      if(error){script.remove();factoryPromise=null;reject(error);}else resolve(factory);
    };
    const ready=factory=>{finish(null,factory);if(typeof previous==='function')previous(factory);};
    const timer=setTimeout(()=>finish(new Error('Player API timeout')),15000);
    window.onKinescopeIframeAPIReady=ready;
    script.src='https://player.kinescope.io/latest/iframe.player.js';
    script.async=true;
    script.onerror=()=>finish(new Error('Player API unavailable'));
    document.head.appendChild(script);
  });
  return factoryPromise;
}

export function mountKinescope(iframe,onPlaying,{getFactory=loadFactory,onUnavailable=()=>{}}={}) {
  let disposed=false,creating=null,player=null,listener=null;
  const id=`lesson-video-${++sequence}`;
  iframe.id=id;
  const status=iframe.parentElement.querySelector('[data-player-status]');
  const url=iframe.dataset.kinescopeUrl;
  getFactory().then(factory=>{
    // Leaving a lesson while the SDK loads must not create a stale player.
    if(disposed||!iframe.isConnected)return;
    creating=factory.create(id,{url,size:{width:'100%',height:'100%'},behavior:{preload:false,autoPlay:false},ui:{language:'ru'}});
    return creating.then(instance=>{
      player=instance;
      if(disposed)return;
      if(status)status.hidden=true;
      listener=()=>{if(!disposed&&iframe.isConnected)onPlaying();};
      // Playing confirms actual playback, unlike Play (a playback request).
      player.once(player.Events.Playing,listener);
    });
  }).catch(()=>{
    if(disposed||!iframe.isConnected)return;
    if(status)status.hidden=true;
    // Keep the video and manual completion usable if the external SDK fails.
    iframe.src=iframe.dataset.embedSrc;
    onUnavailable();
  });
  return async()=>{
    disposed=true;
    if(creating){
      try{
        const instance=player||await creating;
        if(listener)instance.off(instance.Events.Playing,listener);
        // Finish teardown before the application removes the iframe.
        await instance.destroy();
      }catch{ /* A failed player has no live instance to clean up. */ }
    }
  };
}
