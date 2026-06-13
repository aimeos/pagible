import{a as c}from"./graphql-JRtEtyn0.js";import{G as u,H as m,J as l,K as d,L as f,w as g}from"./index-vFujDlHb.js";import{toMp3 as p,transcription as $}from"./audio-CvQuggF0.js";import"./shared-C1gvLhAf.js";import"./ckeditor-D9GR95Rm.js";import"./markdown-JOCB_Zgw.js";const x=c`
  mutation ($file: Upload!) {
    transcribe(file: $file)
  }
`,h=c`
  mutation ($texts: [String!]!, $to: String!, $from: String, $context: String) {
    translate(texts: $texts, to: $to, from: $from, context: $context)
  }
`,w=c`
  mutation ($prompt: String!, $context: String, $files: [String!]) {
    write(prompt: $prompt, context: $context, files: $files)
  }
`;function T(e){const n=u(),o=m(),{$gettext:a}=l;if(!n.can("audio:transcribe")){o.add(a("Permission denied"),"error");return}return p(d(e,!0)).then(r=>f.mutate({mutation:x,variables:{file:new File([r],"audio.mp3",{type:"audio/mpeg"})},context:{hasUpload:!0}})).then(r=>{if(r.errors)throw r;return $(g(r.data?.transcribe||"[]",[]))}).catch(r=>{o.add(a("Error transcribing file")+`:
`+r,"error"),console.error("useAi::transcribe(): Error transcribing from media URL",r)})}function U(e,n,o=null,a=null){const r=u(),s=m(),{$gettext:t}=l;if(!r.can("text:translate")){s.add(t("Permission denied"),"error");return}return Array.isArray(e)||(e=[e].filter(i=>!!i)),e.length?n?f.mutate({mutation:h,variables:{texts:e,to:n.toUpperCase(),from:o?.toUpperCase(),context:a}}).then(i=>{if(i.errors)throw i;return i.data?.translate||[]}).catch(i=>{s.add(t("Error translating texts")+`:
`+i,"error"),console.error("useAi::translate(): Error translating texts",i)}):Promise.reject(new Error("Target language is required")):Promise.resolve([])}function v(e,n=[],o=[]){const a=u(),r=m(),{$gettext:s}=l;if(!a.can("text:write")){r.add(s("Permission denied"),"error");return}return e=String(e).trim(),e?(Array.isArray(n)||(n=[n]),n.push("Only return the requested data without any additional information"),f.mutate({mutation:w,variables:{prompt:e,context:n.filter(t=>!!t).join(`
`),files:o.filter(t=>!!t)}}).then(t=>{if(t.errors)throw t;return t.data?.write?.replace(/^"(.*)"$/,"$1")||""}).catch(t=>{r.add(s("Error generating text")+`:
`+t,"error"),console.error("useAi::write(): Error generating text",t)})):(r.add(s("Prompt is required for generating text"),"error"),Promise.resolve(""))}export{T as transcribe,U as translate,v as write};
