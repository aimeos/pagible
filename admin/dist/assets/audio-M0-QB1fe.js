function e(){let e,t,n,r=!1,i=[],a;return{async start(){if(r)return;r=!0;let o=new Blob([`
        class RecorderProcessor extends AudioWorkletProcessor {
          process(inputs) {
            const input = inputs[0]
            if (input && input[0]) this.port.postMessage(input[0])
            return true
          }
        }
        registerProcessor('recorder-processor', RecorderProcessor)
      `],{type:`application/javascript`});e=new AudioContext,a=URL.createObjectURL(o),await e.audioWorklet.addModule(a),n=new AudioWorkletNode(e,`recorder-processor`),n.port.onmessage=e=>{i.length<3e3&&i.push(new Float32Array(e.data))};let s=await navigator.mediaDevices.getUserMedia({audio:!0});return t=e.createMediaStreamSource(s),t.connect(n),this},async stop(){if(!r||!n||!t)return;r=!1,t.mediaStream.getTracks().forEach(e=>e.stop()),t.disconnect(),n.disconnect(),await e.close(),e=null,a&&=(URL.revokeObjectURL(a),null);let o=i.reduce((e,t)=>e+t.length,0),s=new Float32Array(o),c=0;for(let e of i)s.set(e,c),c+=e.length;if(i=[],!s.length)return null;let l=new OfflineAudioContext(1,s.length,44100),u=l.createBuffer(1,s.length,44100);return u.getChannelData(0).set(s),l.startRendering().catch(()=>{}),u}}}async function t(e){let t=[],n;if(e instanceof AudioBuffer)n=e;else{let t=new AudioContext;try{if(e instanceof ArrayBuffer)n=await t.decodeAudioData(e);else if(typeof e==`string`||e instanceof URL)n=await t.decodeAudioData(await(await fetch(e)).arrayBuffer());else if(e instanceof Blob)n=await t.decodeAudioData(await e.arrayBuffer());else throw Error(`toMp3(): Unsupported input type. Expected URL, Blob, ArrayBuffer, or AudioBuffer.`)}finally{await t.close()}}for(let e=0;e<n.numberOfChannels;e++)t.push(n.getChannelData(e));let r=new Worker(new URL(``+new URL(`worker-DEMcgARQ.js`,import.meta.url).href,``+import.meta.url),{type:`module`});return new Promise((e,i)=>{r.onmessage=t=>{r.terminate(),e(t.data)},r.onerror=e=>{r.terminate(),i(e)},r.postMessage({channels:t,length:n.length,sampleRate:n.sampleRate},t.map(e=>e.buffer))})}function n(e=[]){return{asText(t=`
`){return e.map(e=>e.text??``).join(t)},asVTT(){return`WEBVTT

`+e.map((e,t)=>{let{start:n=0,end:r=0,text:i=``}=e,a=e=>{let t=Math.floor(e%1*1e3),n=Math.floor(e);return`${String(Math.floor(n/3600)).padStart(2,`0`)}:${String(Math.floor(n%3600/60)).padStart(2,`0`)}:${String(n%60).padStart(2,`0`)}.${String(t).padStart(3,`0`)}`};return`${t+1}\n${a(n)} --> ${a(r)}\n${i}\n`}).join(`
`)}}}export{e as recording,t as toMp3,n as transcription};