function p(){let e,o,t,n=!1,r=[],c;return{async start(){if(n)return;n=!0;const a=new Blob([`
        class RecorderProcessor extends AudioWorkletProcessor {
          process(inputs) {
            const input = inputs[0]
            if (input && input[0]) this.port.postMessage(input[0])
            return true
          }
        }
        registerProcessor('recorder-processor', RecorderProcessor)
      `],{type:"application/javascript"});e=new AudioContext,c=URL.createObjectURL(a),await e.audioWorklet.addModule(c),t=new AudioWorkletNode(e,"recorder-processor"),t.port.onmessage=l=>{r.length<3e3&&r.push(new Float32Array(l.data))};const s=await navigator.mediaDevices.getUserMedia({audio:!0});return o=e.createMediaStreamSource(s),o.connect(t),this},async stop(){if(!n||!t||!o)return;n=!1,o.mediaStream.getTracks().forEach(i=>i.stop()),o.disconnect(),t.disconnect(),await e.close(),e=null,c&&(URL.revokeObjectURL(c),c=null);const a=r.reduce((i,d)=>i+d.length,0),s=new Float32Array(a);let l=0;for(const i of r)s.set(i,l),l+=i.length;if(r=[],!s.length)return null;const u=new OfflineAudioContext(1,s.length,44100),f=u.createBuffer(1,s.length,44100);return f.getChannelData(0).set(s),u.startRendering().catch(()=>{}),f}}}async function h(e){const o=[];let t;if(e instanceof AudioBuffer)t=e;else{const r=new AudioContext;try{if(e instanceof ArrayBuffer)t=await r.decodeAudioData(e);else if(typeof e=="string"||e instanceof URL)t=await r.decodeAudioData(await(await fetch(e)).arrayBuffer());else if(e instanceof Blob)t=await r.decodeAudioData(await e.arrayBuffer());else throw new Error("toMp3(): Unsupported input type. Expected URL, Blob, ArrayBuffer, or AudioBuffer.")}finally{await r.close()}}for(let r=0;r<t.numberOfChannels;r++)o.push(t.getChannelData(r));const n=new Worker(new URL(""+new URL("worker-BVqhk6Xq.js",import.meta.url).href,import.meta.url),{type:"module"});return new Promise((r,c)=>{n.onmessage=a=>{n.terminate(),r(a.data)},n.onerror=a=>{n.terminate(),c(a)},n.postMessage({channels:o,length:t.length,sampleRate:t.sampleRate},o.map(a=>a.buffer))})}function g(e=[]){return{asText(o=`
`){return e.map(t=>t.text??"").join(o)},asVTT(){return`WEBVTT

`+e.map((o,t)=>{const{start:n=0,end:r=0,text:c=""}=o,a=s=>{const l=Math.floor(s%1*1e3),u=Math.floor(s),f=String(Math.floor(u/3600)).padStart(2,"0"),i=String(Math.floor(u%3600/60)).padStart(2,"0"),d=String(u%60).padStart(2,"0");return`${f}:${i}:${d}.${String(l).padStart(3,"0")}`};return`${t+1}
${a(n)} --> ${a(r)}
${c}
`}).join(`
`)}}}export{p as recording,h as toMp3,g as transcription};
