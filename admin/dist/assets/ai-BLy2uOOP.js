import{a9 as d,ak as m,Q as g,al as p,y as h}from"./index-CNhgFaQB.js";import{u as $}from"./utils-CCQ59qwa.js";function b(){let e,r,a,o=!1;const n=[];return{async start(){if(o)return;o=!0;const s=new Blob([`
        class RecorderProcessor extends AudioWorkletProcessor {
          process(inputs) {
            const input = inputs[0]
            if (input && input[0]) this.port.postMessage(input[0])
            return true
          }
        }
        registerProcessor('recorder-processor', RecorderProcessor)
      `],{type:"application/javascript"});e=new AudioContext,await e.audioWorklet.addModule(URL.createObjectURL(s)),a=new AudioWorkletNode(e,"recorder-processor"),a.port.onmessage=i=>{n.push(new Float32Array(i.data))};const t=await navigator.mediaDevices.getUserMedia({audio:!0});return r=e.createMediaStreamSource(t),r.connect(a),this},async stop(){if(!o||!a||!r)return;o=!1,r.disconnect(),a.disconnect(),await e.close();const s=n.reduce((u,f)=>u+f.length,0),t=new Float32Array(s);let i=0;for(const u of n)t.set(u,i),i+=u.length;if(!t.length)return null;const l=new AudioContext,c=l.createBuffer(1,t.length,44100);return c.getChannelData(0).set(t),await l.close(),c}}}async function x(e){const r=new AudioContext,a=[];let o;if(e instanceof AudioBuffer)o=e;else if(e instanceof ArrayBuffer)o=await r.decodeAudioData(e);else if(typeof e=="string"||e instanceof URL)o=await r.decodeAudioData(await(await fetch(e)).arrayBuffer());else if(e instanceof Blob)o=await r.decodeAudioData(await e.arrayBuffer());else throw new Error("toMp3(): Unsupported input type. Expected URL, Blob, ArrayBuffer, or AudioBuffer.");for(let s=0;s<o.numberOfChannels;s++)a.push(o.getChannelData(s));await r.close();const n=new Worker(new URL(""+new URL("worker-BVqhk6Xq.js",import.meta.url).href,import.meta.url),{type:"module"});return new Promise(s=>{n.onmessage=t=>s(t.data),n.postMessage({channels:a,length:o.length,sampleRate:o.sampleRate},a.map(t=>t.buffer))})}function S(e=[]){return{asText(r=`
`){return e.map(a=>a.text??"").join(r)},asVTT(){return`WEBVTT

`+e.map((r,a)=>{const{start:o=0,end:n=0,text:s=""}=r,t=i=>{const l=Math.floor(i%1*1e3),c=Math.floor(i),u=String(Math.floor(c/3600)).padStart(2,"0"),f=String(Math.floor(c%3600/60)).padStart(2,"0"),w=String(c%60).padStart(2,"0");return`${u}:${f}:${w}.${String(l).padStart(3,"0")}`};return`${a+1}
${t(o)} --> ${t(n)}
${s}
`}).join(`
`)}}}function B(e){const r=d(),a=m(),{$gettext:o}=g;if(!r.can("audio:transcribe")){a.add(o("Permission denied"),"error");return}return x($(e,!0)).then(n=>p.mutate({mutation:h`
          mutation ($file: Upload!) {
            transcribe(file: $file)
          }
        `,variables:{file:new File([n],"audio.mp3",{type:"audio/mpeg"})},context:{hasUpload:!0}})).then(n=>{if(n.errors)throw n;return S(JSON.parse(n.data?.transcribe||"[]"))}).catch(n=>{a.add(o("Error transcribing file")+`:
`+n,"error"),console.error("useAi::transcribe(): Error transcribing from media URL",n)})}function U(e,r,a=null,o=null){const n=d(),s=m(),{$gettext:t}=g;if(!n.can("text:translate")){s.add(t("Permission denied"),"error");return}return Array.isArray(e)||(e=[e].filter(i=>!!i)),e.length?r?p.mutate({mutation:h`
        mutation ($texts: [String!]!, $to: String!, $from: String, $context: String) {
          translate(texts: $texts, to: $to, from: $from, context: $context)
        }
      `,variables:{texts:e,to:r.toUpperCase(),from:a?.toUpperCase(),context:o}}).then(i=>{if(i.errors)throw i;return i.data?.translate||[]}).catch(i=>{s.add(t("Error translating texts")+`:
`+i,"error"),console.error("useAi::translate(): Error translating texts",i)}):Promise.reject(new Error("Target language is required")):Promise.resolve([])}function M(e,r=[],a=[]){const o=d(),n=m(),{$gettext:s}=g;if(!o.can("text:write")){n.add(s("Permission denied"),"error");return}return e=String(e).trim(),e?(Array.isArray(r)||(r=[r]),r.push("Only return the requested data without any additional information"),p.mutate({mutation:h`
        mutation ($prompt: String!, $context: String, $files: [String!]) {
          write(prompt: $prompt, context: $context, files: $files)
        }
      `,variables:{prompt:e,context:r.filter(t=>!!t).join(`
`),files:a.filter(t=>!!t)}}).then(t=>{if(t.errors)throw t;return t.data?.write?.replace(/^"(.*)"$/,"$1")||""}).catch(t=>{n.add(s("Error generating text")+`:
`+t,"error"),console.error("useAi::write(): Error generating text",t)})):(n.add(s("Prompt is required for generating text"),"error"),Promise.resolve(""))}export{U as a,b as r,B as t,M as w};
