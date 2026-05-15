import{f as c}from"./graphql-CvgRNBEg.js";import{dg as u,d1 as m,av as f,U as l}from"./index-B5TNd1k_.js";import{toMp3 as d,transcription as g}from"./audio-CvQuggF0.js";import{k as p}from"./utils-D5Fs6vLJ.js";import"./graphql-upload-6RPwClA1.js";import"./tree-BEgJ7c65.js";const $=c`
  mutation ($file: Upload!) {
    transcribe(file: $file)
  }
`,x=c`
  mutation ($texts: [String!]!, $to: String!, $from: String, $context: String) {
    translate(texts: $texts, to: $to, from: $from, context: $context)
  }
`,h=c`
  mutation ($prompt: String!, $context: String, $files: [String!]) {
    write(prompt: $prompt, context: $context, files: $files)
  }
`;function P(e){const n=u(),o=m(),{$gettext:a}=f;if(!n.can("audio:transcribe")){o.add(a("Permission denied"),"error");return}return d(p(e,!0)).then(r=>l.mutate({mutation:$,variables:{file:new File([r],"audio.mp3",{type:"audio/mpeg"})},context:{hasUpload:!0}})).then(r=>{if(r.errors)throw r;return g(JSON.parse(r.data?.transcribe||"[]"))}).catch(r=>{o.add(a("Error transcribing file")+`:
`+r,"error"),console.error("useAi::transcribe(): Error transcribing from media URL",r)})}function T(e,n,o=null,a=null){const r=u(),s=m(),{$gettext:t}=f;if(!r.can("text:translate")){s.add(t("Permission denied"),"error");return}return Array.isArray(e)||(e=[e].filter(i=>!!i)),e.length?n?l.mutate({mutation:x,variables:{texts:e,to:n.toUpperCase(),from:o?.toUpperCase(),context:a}}).then(i=>{if(i.errors)throw i;return i.data?.translate||[]}).catch(i=>{s.add(t("Error translating texts")+`:
`+i,"error"),console.error("useAi::translate(): Error translating texts",i)}):Promise.reject(new Error("Target language is required")):Promise.resolve([])}function U(e,n=[],o=[]){const a=u(),r=m(),{$gettext:s}=f;if(!a.can("text:write")){r.add(s("Permission denied"),"error");return}return e=String(e).trim(),e?(Array.isArray(n)||(n=[n]),n.push("Only return the requested data without any additional information"),l.mutate({mutation:h,variables:{prompt:e,context:n.filter(t=>!!t).join(`
`),files:o.filter(t=>!!t)}}).then(t=>{if(t.errors)throw t;return t.data?.write?.replace(/^"(.*)"$/,"$1")||""}).catch(t=>{r.add(s("Error generating text")+`:
`+t,"error"),console.error("useAi::write(): Error generating text",t)})):(r.add(s("Prompt is required for generating text"),"error"),Promise.resolve(""))}export{P as transcribe,T as translate,U as write};
